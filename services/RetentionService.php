<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormAnswer;
use Yii;
use yii\db\Query;

/**
 * Retention (GOV-8): email logs, abandoned drafts and integrity client hashes are removed
 * after a configurable number of days (module settings; 0 turns a rule off). Responses that
 * were completed, consent records and audit trails are never touched here.
 */
class RetentionService
{
    public const SETTING_EMAIL_LOG_DAYS = 'retention_email_log_days';
    public const SETTING_DRAFT_DAYS = 'retention_draft_days';
    public const SETTING_INTEGRITY_HASH_DAYS = 'retention_integrity_hash_days';

    public const DEFAULTS = [
        self::SETTING_EMAIL_LOG_DAYS => 365,
        self::SETTING_DRAFT_DAYS => 180,
        self::SETTING_INTEGRITY_HASH_DAYS => 90,
    ];

    public static function days(string $setting): int
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        $raw = $module ? $module->settings->get($setting) : null;
        return max(0, (int)($raw === null || $raw === '' ? self::DEFAULTS[$setting] : $raw));
    }

    /** Once a day from the hourly cron. */
    public function runDaily(): ?array
    {
        $key = 'cf-retention-' . gmdate('Y-m-d');
        if (Yii::$app->cache->get($key)) {
            return null;
        }
        Yii::$app->cache->set($key, 1, 90000);
        return $this->run(false);
    }

    /**
     * @return array{email_log:int,drafts:int,integrity_hashes:int}
     */
    public function run(bool $dryRun): array
    {
        $db = Yii::$app->db;
        $has = static fn(string $table): bool => $db->schema->getTableSchema($table, true) !== null;
        $cutoff = static fn(int $days): string => gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $out = ['email_log' => 0, 'drafts' => 0, 'integrity_hashes' => 0];

        $days = self::days(self::SETTING_EMAIL_LOG_DAYS);
        if ($days > 0 && $has('{{%form_email_send}}')) {
            $where = ['<', 'created_at', $cutoff($days)];
            $out['email_log'] = (int)(new Query())->from('{{%form_email_send}}')->where($where)->count();
            if (!$dryRun) {
                $db->createCommand()->delete('{{%form_email_send}}', $where)->execute();
            }
        }

        $days = self::days(self::SETTING_DRAFT_DAYS);
        if ($days > 0) {
            $query = FormAnswer::find()
                ->where(['status' => FormAnswer::STATUS_IN_PROGRESS])
                ->andWhere(['<', 'updated_at', $cutoff($days)]);
            $out['drafts'] = (int)(clone $query)->count();
            if (!$dryRun) {
                foreach ($query->each(100) as $draft) {
                    $draft->delete();
                }
            }
        }

        $days = self::days(self::SETTING_INTEGRITY_HASH_DAYS);
        if ($days > 0 && $has('custom_form_integrity_meta')) {
            $where = ['and',
                ['<', 'completed_at', $cutoff($days)],
                ['or', ['not', ['ip_hash' => null]], ['not', ['ip_network_hash' => null]], ['not', ['session_hash' => null]], ['not', ['user_agent_hash' => null]]],
            ];
            $out['integrity_hashes'] = (int)(new Query())->from('custom_form_integrity_meta')->where($where)->count();
            if (!$dryRun) {
                $db->createCommand()->update('custom_form_integrity_meta', [
                    'ip_hash' => null,
                    'ip_network_hash' => null,
                    'session_hash' => null,
                    'user_agent_hash' => null,
                ], $where)->execute();
            }
        }
        return $out;
    }
}
