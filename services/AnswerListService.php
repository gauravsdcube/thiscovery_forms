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
        $filters = self::normalize($params);
        $query = $form->getExportableAnswers()
            ->joinWith('user')
            ->with(['user', 'wave', 'round', 'panelMember', 'currentStage']);
        $query->orderBy([]);

        if ($filters['status'] === 'complete') {
            $query->andWhere(['custom_form_answer.status' => FormAnswer::STATUS_COMPLETE]);
        } elseif ($filters['status'] === 'progress') {
            $query->andWhere(['custom_form_answer.status' => FormAnswer::STATUS_IN_PROGRESS]);
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
                ],
            ],
        ]);

        return [$provider, $filters];
    }

    public static function normalize(array $params): array
    {
        $status = (string)($params['status'] ?? '');
        if (!in_array($status, ['complete', 'progress'], true)) {
            $status = '';
        }

        $pageSize = (int)($params['per-page'] ?? self::DEFAULT_PAGE_SIZE);
        if (!in_array($pageSize, self::PAGE_SIZES, true)) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        return [
            'q' => trim((string)($params['q'] ?? '')),
            'status' => $status,
            'pageSize' => $pageSize,
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
            ->with(['user', 'answerFields.field', 'wave', 'round', 'panelMember', 'currentStage'])
            ->one();

        return $answer;
    }
}
