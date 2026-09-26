<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services\dashboard;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\thiscoveryDashboard\interfaces\DataProviderInterface;
use humhub\modules\thiscoveryDashboard\interfaces\QuestionTypeInterface;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\user\models\User;
use Yii;

class FormsDataProvider implements DataProviderInterface
{
    public function getId(): string
    {
        return 'forms';
    }

    public function getLabel(): string
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Forms');
    }

    public function listSources(?ContentContainerActiveRecord $container = null, ?User $viewer = null): array
    {
        $query = CustomForm::find()->joinWith('content');
        if ($container) {
            $containerId = (int)$container->contentcontainer_id;
            $query->andWhere([
                'or',
                ['content.contentcontainer_id' => $containerId],
                ['content.contentcontainer_id' => null],
            ]);
        }
        $out = [];
        foreach ($query->all() as $form) {
            try {
                if ($form->isTemplate()) {
                    continue;
                }
                if ($viewer && !$this->canListForm($form, $viewer)) {
                    continue;
                }
                $out[] = [
                    'id' => (string)$form->id,
                    'label' => $form->title,
                    'containerId' => $form->content->contentcontainer_id ? (int)$form->content->contentcontainer_id : null,
                ];
            } catch (\Throwable $e) {
                Yii::warning('Dashboard skipped form ' . (int)$form->id . ': ' . $e->getMessage(), 'thiscovery-forms');
            }
        }
        return $out;
    }

    private function canListForm(CustomForm $form, User $viewer): bool
    {
        if ($form->canViewAnswers($viewer) || $form->canManage($viewer)) {
            return true;
        }
        try {
            return (bool)$form->content->canView($viewer);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function listQuestions(string $sourceId, ?User $viewer = null): array
    {
        $form = $this->form($sourceId);
        if (!$form) {
            return [];
        }
        $out = [];
        foreach ($form->getAllFields()->all() as $field) {
            if (!$field->collectsAnswer()) {
                continue;
            }
            $type = QuestionTypeRegistry::forField($field);
            $out[] = [
                'id' => (string)$field->id,
                'label' => $field->label,
                'typeId' => $type->getId(),
                'typeLabel' => $type->getLabel(),
                'dataShape' => $type->getDataShape(),
                'defaultVisualisations' => $type->getDefaultVisualisations(),
                'meta' => $this->metaFromField($field),
            ];
        }
        return $out;
    }

    public function listSegmentFields(string $sourceId, ?User $viewer = null): array
    {
        $form = $this->form($sourceId);
        if (!$form) {
            return [];
        }
        $out = [
            ['id' => 'wave_id', 'label' => Yii::t('ThiscoveryFormsModule.base', 'Wave')],
        ];
        foreach ($form->getAllFields()->all() as $field) {
            if (in_array($field->type, [FormField::TYPE_DROPDOWN, FormField::TYPE_RADIO, FormField::TYPE_PANEL_ATTR], true)) {
                $out[] = ['id' => 'q:' . $field->id, 'label' => $field->label];
            }
        }
        return $out;
    }

    public function getQuestionType(string $sourceId, string $questionId): ?QuestionTypeInterface
    {
        $field = FormField::findOne(['id' => (int)$questionId, 'form_id' => (int)$sourceId]);
        if (!$field) {
            return null;
        }
        return QuestionTypeRegistry::forField($field);
    }

    public function getQuestionMeta(string $sourceId, string $questionId): array
    {
        $field = FormField::findOne(['id' => (int)$questionId, 'form_id' => (int)$sourceId]);
        return $field ? $this->metaFromField($field) : [];
    }

    public function loadResponse(string $sourceId, int $responseId): ?array
    {
        $q = FormAnswer::find()->alias('a')->where([
            'a.id' => $responseId,
            'a.form_id' => (int)$sourceId,
            'a.status' => FormAnswer::STATUS_COMPLETE,
            'a.is_test' => 0,
        ]);
        FormIntegrityMeta::scopeIncludedInAnalysis($q);
        /** @var FormAnswer|null $answer */
        $answer = $q->one();
        if (!$answer) {
            return null;
        }
        return $this->packAnswer($answer);
    }

    public function eachResponse(string $sourceId, callable $each, int $chunkSize = 200, int $afterId = 0): int
    {
        $count = 0;
        $afterId = max(0, $afterId);
        $chunkSize = max(50, $chunkSize);
        while (true) {
            $q = FormAnswer::find()->alias('a')
                ->where([
                    'a.form_id' => (int)$sourceId,
                    'a.status' => FormAnswer::STATUS_COMPLETE,
                    'a.is_test' => 0,
                ])
                ->andWhere(['>', 'a.id', $afterId])
                ->orderBy(['a.id' => SORT_ASC])
                ->limit($chunkSize);
            FormIntegrityMeta::scopeIncludedInAnalysis($q);
            $rows = $q->all();
            if (!$rows) {
                break;
            }
            foreach ($rows as $answer) {
                $each($this->packAnswer($answer));
                $afterId = (int)$answer->id;
                $count++;
            }
            if (count($rows) < $chunkSize) {
                break;
            }
        }
        return $count;
    }

    public function canViewSource(string $sourceId, ?User $viewer = null): bool
    {
        $form = $this->form($sourceId);
        if (!$form) {
            return false;
        }
        if (!$viewer) {
            return false;
        }
        return $this->canListForm($form, $viewer);
    }

    public function queryResponses(string $sourceId, array $filters, int $offset = 0, int $limit = 50, ?User $viewer = null): array
    {
        if (!$this->canViewSource($sourceId, $viewer)) {
            return ['rows' => [], 'total' => 0, 'offset' => $offset, 'limit' => $limit];
        }
        $limit = min(50, max(1, $limit));
        $q = FormAnswer::find()->alias('a')->where([
            'a.form_id' => (int)$sourceId,
            'a.status' => FormAnswer::STATUS_COMPLETE,
            'a.is_test' => 0,
        ]);
        FormIntegrityMeta::scopeIncludedInAnalysis($q);
        if (!empty($filters['from'])) {
            $q->andWhere(['>=', 'a.created_at', $filters['from'] . ' 00:00:00']);
        }
        if (!empty($filters['to'])) {
            $q->andWhere(['<=', 'a.created_at', $filters['to'] . ' 23:59:59']);
        }
        if (!empty($filters['wave_id'])) {
            $q->andWhere(['a.wave_id' => $filters['wave_id']]);
        }
        $ids = array_values(array_filter(array_map('intval', (array)($filters['responseIds'] ?? []))));
        if ($ids) {
            $q->andWhere(['a.id' => $ids]);
        }
        $total = (int)(clone $q)->count();
        $answers = $q->orderBy(['a.id' => SORT_DESC])->offset($offset)->limit($limit)->all();
        $questionId = (int)($filters['questionId'] ?? 0);
        $rows = [];
        foreach ($answers as $answer) {
            $display = '';
            if ($questionId) {
                $display = (string)$answer->getFieldValue($questionId);
            }
            $rows[] = [
                'id' => (int)$answer->id,
                'createdAt' => $answer->created_at,
                'value' => mb_substr($display, 0, 200),
            ];
        }
        return ['rows' => $rows, 'total' => $total, 'offset' => $offset, 'limit' => $limit];
    }

    private function form(string $sourceId): ?CustomForm
    {
        return CustomForm::findOne((int)$sourceId);
    }

    /**
     * @return array{id:int,createdAt:string,segments:array<string,string>,values:array<string,mixed>}
     */
    private function packAnswer(FormAnswer $answer): array
    {
        $values = [];
        foreach ($answer->answerFields as $af) {
            $values[(string)$af->field_id] = $af->value;
        }
        $segments = [];
        if ($answer->wave_id) {
            $segments['wave_id'] = (string)$answer->wave_id;
        }
        return [
            'id' => (int)$answer->id,
            'createdAt' => (string)$answer->created_at,
            'weight' => (float)($answer->weight ?: 1),
            'waveId' => $answer->wave_id ? (string)$answer->wave_id : '',
            'segments' => $segments,
            'values' => $values,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metaFromField(FormField $field): array
    {
        $meta = ['type' => $field->type];
        try {
            $pairs = $field->getChoicePairs();
            if ($pairs) {
                $meta['options'] = $pairs;
            }
        } catch (\Throwable $e) {
        }
        if (in_array($field->type, [FormField::TYPE_GRID_SINGLE, FormField::TYPE_GRID_MULTI], true)) {
            $meta['grid'] = $field->getGridConfig();
        }
        return $meta;
    }
}
