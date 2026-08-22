<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Expression;

class FormListService
{
    public const PAGE_SIZES = [10, 25, 50];
    public const DEFAULT_PAGE_SIZE = 25;

    /**
     * @return array{0: ActiveDataProvider, 1: array}
     */
    public static function provider(ActiveQuery $query, array $params = [], $container = null): array
    {
        $filters = self::normalize($params);

        if ($filters['kind'] !== '') {
            $query->andWhere(['custom_form.kind' => $filters['kind']]);
        }
        if ($filters['status'] !== '') {
            $query->andWhere(['custom_form.status' => (int)$filters['status']]);
        }
        if ($filters['q'] !== '') {
            $query->andWhere([
                'or',
                ['like', 'custom_form.title', $filters['q']],
                ['like', 'custom_form.description', $filters['q']],
            ]);
        }

        FolderService::applyListFilter($query, $container, $filters);

        if ($query->select === null) {
            $query->select(['custom_form.*']);
        }
        $query->with('content');
        $query->addSelect([
            'field_count' => new Expression('(SELECT COUNT(*) FROM custom_form_field WHERE custom_form_field.form_id = custom_form.id)'),
            'answer_count' => new Expression('(SELECT COUNT(*) FROM custom_form_answer WHERE custom_form_answer.form_id = custom_form.id AND custom_form_answer.is_test = 0)'),
        ]);

        $provider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $filters['pageSize'],
                'pageSizeParam' => 'per-page',
                'pageSizeLimit' => [10, 50],
            ],
            'sort' => [
                'defaultOrder' => ['updated_at' => SORT_DESC],
                'attributes' => [
                    'title' => [
                        'asc' => ['custom_form.title' => SORT_ASC],
                        'desc' => ['custom_form.title' => SORT_DESC],
                    ],
                    'kind' => [
                        'asc' => ['custom_form.kind' => SORT_ASC],
                        'desc' => ['custom_form.kind' => SORT_DESC],
                    ],
                    'status' => [
                        'asc' => ['custom_form.status' => SORT_ASC],
                        'desc' => ['custom_form.status' => SORT_DESC],
                    ],
                    'field_count' => [
                        'asc' => ['field_count' => SORT_ASC],
                        'desc' => ['field_count' => SORT_DESC],
                    ],
                    'answer_count' => [
                        'asc' => ['answer_count' => SORT_ASC],
                        'desc' => ['answer_count' => SORT_DESC],
                    ],
                    'created_at' => [
                        'asc' => ['content.created_at' => SORT_ASC],
                        'desc' => ['content.created_at' => SORT_DESC],
                    ],
                    'updated_at' => [
                        'asc' => ['content.updated_at' => SORT_ASC],
                        'desc' => ['content.updated_at' => SORT_DESC],
                    ],
                ],
            ],
        ]);

        return [$provider, $filters];
    }

    public static function normalize(array $params): array
    {
        $kind = (string)($params['kind'] ?? '');
        if ($kind !== '' && !isset(CustomForm::getKindLabels()[$kind])) {
            $kind = '';
        }

        $status = (string)($params['status'] ?? '');
        if ($status !== '' && !in_array((int)$status, [
            CustomForm::STATUS_DRAFT,
            CustomForm::STATUS_OPEN,
            CustomForm::STATUS_CLOSED,
        ], true)) {
            $status = '';
        }

        $pageSize = (int)($params['per-page'] ?? self::DEFAULT_PAGE_SIZE);
        if (!in_array($pageSize, self::PAGE_SIZES, true)) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }

        return [
            'q' => trim((string)($params['q'] ?? '')),
            'kind' => $kind,
            'status' => $status,
            'pageSize' => $pageSize,
            'folder' => max(0, (int)($params['folder'] ?? 0)),
        ];
    }
}
