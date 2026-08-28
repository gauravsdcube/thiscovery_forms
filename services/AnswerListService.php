<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use yii\data\ActiveDataProvider;

class AnswerListService
{
    public const PAGE_SIZES = [10, 25, 50];
    public const DEFAULT_PAGE_SIZE = 25;

    /**
     * @return array{0: ActiveDataProvider, 1: array}
     */
    public static function provider(CustomForm $form, array $params = []): array
    {
        [$query, $filters] = self::query($form, $params);
        $query->orderBy([]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $filters['pageSize'],
                'pageSizeParam' => 'per-page',
                'pageSizeLimit' => [10, 50],
            ],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC],
                'attributes' => [
                    'created_at' => [
                        'asc' => ['custom_form_answer.created_at' => SORT_ASC],
                        'desc' => ['custom_form_answer.created_at' => SORT_DESC],
                    ],
                    'updated_at' => [
                        'asc' => ['custom_form_answer.updated_at' => SORT_ASC],
                        'desc' => ['custom_form_answer.updated_at' => SORT_DESC],
                    ],
                    'status' => [
                        'asc' => ['custom_form_answer.status' => SORT_ASC],
                        'desc' => ['custom_form_answer.status' => SORT_DESC],
                    ],
                    'submitter' => [
                        'asc' => ['user.username' => SORT_ASC],
                        'desc' => ['user.username' => SORT_DESC],
                    ],
                    'overall_score' => [
                        'asc' => ['custom_form_integrity_meta.overall_score' => SORT_ASC],
                        'desc' => ['custom_form_integrity_meta.overall_score' => SORT_DESC],
                    ],
                    'analysis_status' => [
                        'asc' => ['custom_form_integrity_meta.analysis_status' => SORT_ASC],
                        'desc' => ['custom_form_integrity_meta.analysis_status' => SORT_DESC],
                    ],
                ],
            ],
        ]);

        return [$provider, $filters];
    }

    /**
     * @return array{0: \yii\db\ActiveQuery, 1: array}
     */
    public static function query(CustomForm $form, array $params = []): array
    {
        $filters = self::normalize($params);
        $query = $form->getExportableAnswers()
            ->joinWith(['user', 'integrityMeta'])
            ->with(['user', 'wave', 'round', 'panelMember', 'currentStage', 'integrityMeta']);

        if ($filters['status'] === 'complete') {
            $query->andWhere(['custom_form_answer.status' => FormAnswer::STATUS_COMPLETE]);
        } elseif ($filters['status'] === 'progress') {
            $query->andWhere(['custom_form_answer.status' => FormAnswer::STATUS_IN_PROGRESS]);
        }

        if ($filters['integrity'] !== '') {
            $query->joinWith('integrityMeta');
            if ($filters['integrity'] === 'flag_speed') {
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"category":"speed"']);
            } elseif ($filters['integrity'] === 'flag_duplicate') {
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"category":"duplicate"']);
            } elseif ($filters['integrity'] === 'flag_straightline') {
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"category":"straightline"']);
            } elseif ($filters['integrity'] === 'flag_attention') {
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"category":"attention"']);
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"code":"failed"']);
            } elseif ($filters['integrity'] === 'flag_consistency') {
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"category":"consistency"']);
            } elseif ($filters['integrity'] === 'flag_freetext') {
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"category":"freetext"']);
            } elseif ($filters['integrity'] === 'flag_bot') {
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"category":"bot"']);
            } elseif ($filters['integrity'] === 'flag_similarity') {
                $query->andWhere(['like', 'custom_form_integrity_meta.flags_json', '"category":"similarity"']);
            } else {
                $query->andWhere([
                    'or',
                    ['custom_form_integrity_meta.integrity_status' => $filters['integrity']],
                    ['custom_form_integrity_meta.status_override' => $filters['integrity']],
                    ['custom_form_integrity_meta.analysis_status' => $filters['integrity']],
                ]);
            }
        }

        if ($filters['minScore'] !== null) {
            $query->joinWith('integrityMeta');
            $query->andWhere(['>=', 'custom_form_integrity_meta.overall_score', $filters['minScore']]);
        }

        if (!empty($filters['omitExcluded'])) {
            $query->joinWith('integrityMeta');
            $query->andWhere([
                'or',
                ['custom_form_integrity_meta.id' => null],
                ['<>', 'custom_form_integrity_meta.analysis_status', \humhub\modules\thiscoveryForms\models\FormIntegrityMeta::ANALYSIS_EXCLUDED],
            ]);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->joinWith(['user.profile', 'answerFields'])
                ->andWhere([
                    'or',
                    ['like', 'user.username', $q],
                    ['like', 'profile.firstname', $q],
                    ['like', 'profile.lastname', $q],
                    ['like', 'custom_form_answer.resume_email', $q],
                    ['like', 'custom_form_answer_field.value', $q],
                    ['like', 'custom_form_answer_field.justification', $q],
                ])
                ->distinct();
        }

        return [$query, $filters];
    }

    public static function normalize(array $params): array
    {
        $status = (string)($params['status'] ?? '');
        if (!in_array($status, ['complete', 'progress'], true)) {
            $status = '';
        }
        $integrity = (string)($params['integrity'] ?? '');
        $minScore = isset($params['min_score']) && $params['min_score'] !== ''
            ? (float)$params['min_score']
            : (isset($params['minScore']) && $params['minScore'] !== '' ? (float)$params['minScore'] : null);

        $pageSize = (int)($params['per-page'] ?? self::DEFAULT_PAGE_SIZE);
        if (!in_array($pageSize, self::PAGE_SIZES, true)) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        return [
            'q' => trim((string)($params['q'] ?? '')),
            'status' => $status,
            'integrity' => $integrity,
            'minScore' => $minScore,
            'pageSize' => $pageSize,
            'omitExcluded' => !empty($params['omitExcluded']) || ((string)($params['include_excluded'] ?? '0') !== '1' && !empty($params['forExport'])),
        ];
    }

    public static function findAnswer(CustomForm $form, int $answerId): ?FormAnswer
    {
        /** @var FormAnswer|null $answer */
        $answer = FormAnswer::find()
            ->where([
                'id' => $answerId,
                'form_id' => $form->id,
                'is_test' => 0,
            ])
            ->with(['user', 'answerFields.field', 'wave', 'round', 'panelMember', 'currentStage', 'integrityMeta'])
            ->one();

        return $answer;
    }
}
