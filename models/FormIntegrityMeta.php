<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * Quality metadata stored separately from survey answers.
 *
 * @property int $id
 * @property int $answer_id
 * @property int $form_id
 * @property string|null $started_at
 * @property string|null $completed_at
 * @property int|null $duration_seconds
 * @property int|null $median_seconds
 * @property string|null $page_timings_json
 * @property string|null $question_timings_json
 * @property string|null $ip_hash
 * @property string|null $ip_network_hash
 * @property string|null $session_hash
 * @property string|null $user_agent_hash
 * @property string|null $access_token_hash
 * @property string|null $access_mode
 * @property int $session_established
 * @property int $honeypot_triggered
 * @property int $rate_limited
 * @property int $captcha_shown
 * @property int|null $captcha_passed
 * @property float $bot_score
 * @property float $duplicate_score
 * @property float $speed_score
 * @property float $straightline_score
 * @property float $attention_score
 * @property float $consistency_score
 * @property float $freetext_score
 * @property float $similarity_score
 * @property float $overall_score
 * @property string $integrity_status
 * @property string $analysis_status
 * @property string|null $status_override
 * @property string|null $exclusion_reason
 * @property string|null $flags_json
 * @property string|null $similar_answer_ids_json
 * @property string|null $notes
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read FormAnswer $answer
 * @property-read CustomForm $form
 */
class FormIntegrityMeta extends ActiveRecord
{
    public const STATUS_TRUSTED = 'trusted';
    public const STATUS_REVIEW = 'review';
    public const STATUS_SUSPICIOUS = 'suspicious';
    public const STATUS_EXCLUDED = 'excluded';

    public const ANALYSIS_INCLUDED = 'included';
    public const ANALYSIS_REVIEW = 'review';
    public const ANALYSIS_QUARANTINED = 'quarantined';
    public const ANALYSIS_EXCLUDED = 'excluded';

    public static function tableName()
    {
        return 'custom_form_integrity_meta';
    }

    public function rules()
    {
        return [
            [['answer_id', 'form_id'], 'required'],
            [['answer_id', 'form_id', 'duration_seconds', 'median_seconds', 'session_established', 'honeypot_triggered', 'rate_limited', 'captcha_shown', 'captcha_passed'], 'integer'],
            [['bot_score', 'duplicate_score', 'speed_score', 'straightline_score', 'attention_score', 'consistency_score', 'freetext_score', 'similarity_score', 'overall_score'], 'number'],
            [['integrity_status'], 'in', 'range' => array_keys(self::statusLabels())],
            [['analysis_status'], 'in', 'range' => array_keys(self::analysisLabels())],
            [['status_override'], 'in', 'range' => array_merge([''], array_keys(self::statusLabels()))],
            [['started_at', 'completed_at', 'created_at', 'updated_at', 'page_timings_json', 'question_timings_json', 'flags_json', 'similar_answer_ids_json', 'notes', 'exclusion_reason'], 'safe'],
            [['ip_hash', 'ip_network_hash', 'session_hash', 'user_agent_hash', 'access_token_hash'], 'string', 'max' => 64],
            [['access_mode'], 'string', 'max' => 32],
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_TRUSTED => \Yii::t('ThiscoveryFormsModule.base', 'Trusted'),
            self::STATUS_REVIEW => \Yii::t('ThiscoveryFormsModule.base', 'Review required'),
            self::STATUS_SUSPICIOUS => \Yii::t('ThiscoveryFormsModule.base', 'Suspicious'),
            self::STATUS_EXCLUDED => \Yii::t('ThiscoveryFormsModule.base', 'Excluded'),
        ];
    }

    public static function analysisLabels(): array
    {
        return [
            self::ANALYSIS_INCLUDED => \Yii::t('ThiscoveryFormsModule.base', 'Included'),
            self::ANALYSIS_REVIEW => \Yii::t('ThiscoveryFormsModule.base', 'Review required'),
            self::ANALYSIS_QUARANTINED => \Yii::t('ThiscoveryFormsModule.base', 'Quarantined'),
            self::ANALYSIS_EXCLUDED => \Yii::t('ThiscoveryFormsModule.base', 'Excluded'),
        ];
    }

    public static function analysisDecisionLabels(): array
    {
        return [
            self::ANALYSIS_INCLUDED => \Yii::t('ThiscoveryFormsModule.base', 'Include in analysis'),
            self::ANALYSIS_REVIEW => \Yii::t('ThiscoveryFormsModule.base', 'Hold for review'),
            self::ANALYSIS_QUARANTINED => \Yii::t('ThiscoveryFormsModule.base', 'Quarantine (hold out of the default set)'),
            self::ANALYSIS_EXCLUDED => \Yii::t('ThiscoveryFormsModule.base', 'Exclude from analysis'),
        ];
    }

    public static function componentLabels(): array
    {
        return [
            'bot' => \Yii::t('ThiscoveryFormsModule.base', 'Bot'),
            'duplicate' => \Yii::t('ThiscoveryFormsModule.base', 'Duplicate'),
            'speed' => \Yii::t('ThiscoveryFormsModule.base', 'Speed'),
            'straightline' => \Yii::t('ThiscoveryFormsModule.base', 'Straight-lining'),
            'attention' => \Yii::t('ThiscoveryFormsModule.base', 'Attention'),
            'consistency' => \Yii::t('ThiscoveryFormsModule.base', 'Consistency'),
            'freetext' => \Yii::t('ThiscoveryFormsModule.base', 'Free text'),
            'similarity' => \Yii::t('ThiscoveryFormsModule.base', 'Similarity'),
        ];
    }

    public function getScoreBand(): string
    {
        $score = (float)$this->overall_score;
        if ($score >= 80) {
            return 'ok';
        }
        if ($score >= 55) {
            return 'review';
        }
        return 'low';
    }

    public function getEffectiveStatus(): string
    {
        if ($this->status_override) {
            return $this->status_override;
        }
        return $this->integrity_status ?: self::STATUS_TRUSTED;
    }

    public function getStatusLabel(): string
    {
        $status = $this->getEffectiveStatus();
        return self::statusLabels()[$status] ?? $status;
    }

    public function getAnalysisLabel(): string
    {
        return self::analysisLabels()[$this->analysis_status] ?? $this->analysis_status;
    }

    public function getFlags(): array
    {
        $decoded = json_decode((string)$this->flags_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function isTechnicalFlag(array $flag): bool
    {
        $cat = (string)($flag['category'] ?? '');
        $code = (string)($flag['code'] ?? '');
        if ($cat === 'bot') {
            return true;
        }
        return $cat === 'duplicate' && $code === 'same_source';
    }

    public function getFlagsForViewer(bool $canManage): array
    {
        $flags = $this->getFlags();
        if ($canManage) {
            return $flags;
        }
        return array_values(array_filter($flags, static fn($flag) => is_array($flag) && !self::isTechnicalFlag($flag)));
    }

    public function getComponentScoresForViewer(bool $canManage): array
    {
        $scores = $this->getComponentScores();
        if (!$canManage) {
            unset($scores['bot']);
        }
        return $scores;
    }

    public function setFlags(array $flags): void
    {
        $this->flags_json = $flags ? json_encode(array_values($flags), JSON_UNESCAPED_UNICODE) : null;
    }

    public function getSimilarAnswerIds(): array
    {
        $decoded = json_decode((string)$this->similar_answer_ids_json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('intval', $decoded)));
    }

    public function getPageTimings(): array
    {
        $decoded = json_decode((string)$this->page_timings_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getQuestionTimings(): array
    {
        $decoded = json_decode((string)$this->question_timings_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getComponentScores(): array
    {
        return [
            'bot' => (float)$this->bot_score,
            'duplicate' => (float)$this->duplicate_score,
            'speed' => (float)$this->speed_score,
            'straightline' => (float)$this->straightline_score,
            'attention' => (float)$this->attention_score,
            'consistency' => (float)$this->consistency_score,
            'freetext' => (float)$this->freetext_score,
            'similarity' => (float)$this->similarity_score,
        ];
    }

    public function getAnswer(): ActiveQuery
    {
        return $this->hasOne(FormAnswer::class, ['id' => 'answer_id']);
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function beforeSave($insert)
    {
        $now = date('Y-m-d H:i:s');
        if ($insert && !$this->created_at) {
            $this->created_at = $now;
        }
        $this->updated_at = $now;
        return parent::beforeSave($insert);
    }
}
