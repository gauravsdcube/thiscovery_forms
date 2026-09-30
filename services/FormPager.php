<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormField;
use Yii;

/**
 * Splits ordered fields into pages using page_break markers.
 */
class FormPager
{
    /**
     * @param FormField[] $fields
     * @return array{pages: array<int, array{index:int,pageKey:?string,title:string,break:?FormField,items:FormField[]}>, pageKeyIndex: array<string,int>}
     */
    public function buildPages(array $fields): array
    {
        $pages = [];
        $current = [
            'index' => 0,
            'pageKey' => 'start',
            'title' => '',
            'break' => null,
            'items' => [],
        ];
        $pageKeyIndex = ['start' => 0];

        foreach ($fields as $field) {
            if ($field->type === FormField::TYPE_PAGE_BREAK) {
                $cfg = $field->getPageBreakConfig();
                $current['break'] = $field;
                $pages[] = $current;

                $nextIndex = count($pages);
                $key = $cfg['pageKey'] ?: ('p' . $nextIndex);
                $pageKeyIndex[$key] = $nextIndex;
                $current = [
                    'index' => $nextIndex,
                    'pageKey' => $key,
                    'title' => $cfg['title'] ?: '',
                    'break' => null,
                    'items' => [],
                ];
                continue;
            }
            $current['items'][] = $field;
        }
        $pages[] = $current;

        return [
            'pages' => $pages,
            'pageKeyIndex' => $pageKeyIndex,
        ];
    }

    /**
     * Questions on the page plus the trailing page break, whose Logic runs when leaving this page.
     *
     * @param array{items?:FormField[],break?:FormField|null} $page
     * @return FormField[]
     */
    public static function navigationFields(array $page): array
    {
        $fields = array_values($page['items'] ?? []);
        $break = $page['break'] ?? null;
        if ($break instanceof FormField) {
            $fields[] = $break;
        }
        return $fields;
    }

    /**
     * Whether a question group at $startIndex has a matching group_end on this page.
     *
     * @param FormField[] $items
     */
    public static function groupClosesInItems(array $items, int $startIndex): bool
    {
        $items = array_values($items);
        if (!isset($items[$startIndex]) || $items[$startIndex]->type !== FormField::TYPE_QUESTION_GROUP) {
            return false;
        }
        $depth = 0;
        $count = count($items);
        for ($i = $startIndex; $i < $count; $i++) {
            $type = $items[$i]->type ?? '';
            if ($type === FormField::TYPE_QUESTION_GROUP) {
                $depth++;
            } elseif ($type === FormField::TYPE_GROUP_END && $depth > 0) {
                $depth--;
                if ($depth === 0) {
                    return $i > $startIndex;
                }
            }
        }
        return false;
    }

    /**
     * Field ids on pages the respondent actually reaches with the current answers.
     * Pages jumped over by go-to-page / go-to-end, or skipped because they have
     * nothing visible, are omitted so their required questions are not validated.
     *
     * @param FormField[] $fields
     * @return array<int, true>
     */
    public function visitedFieldIds(array $fields, array $values, array $blockOrders = []): array
    {
        $built = $this->buildPages($fields);
        if ($blockOrders) {
            $built = $this->applyBlockOrder($built, $blockOrders);
        }
        $pages = $built['pages'];
        $pageKeyIndex = $built['pageKeyIndex'];
        $visited = [];
        $idx = 0;
        $limit = count($pages) + 1;
        $guard = 0;
        while (isset($pages[$idx]) && $guard++ < $limit) {
            if (isset($visited[$idx])) {
                Yii::warning('Thiscovery Forms page cycle detected.', 'thiscovery-forms');
                break;
            }
            $visited[$idx] = true;
            $next = $this->resolveNextPage($pages, $pageKeyIndex, $idx, $values, $fields);
            if ($next !== null && isset($visited[(int)$next])) {
                Yii::warning('Thiscovery Forms page cycle detected.', 'thiscovery-forms');
                $next = $this->skipForward($pages, $pageKeyIndex, $idx + 1, $values, new LogicEngine(), $fields);
                if ($next === null || isset($visited[(int)$next])) {
                    break;
                }
            }
            if ($next === null) {
                break;
            }
            $idx = (int)$next;
        }
        $ids = [];
        foreach ($visited as $pageIndex => $_) {
            foreach ($pages[$pageIndex]['items'] as $field) {
                $ids[(int)$field->id] = true;
            }
        }
        return $ids;
    }

    /**
     * @param array $pages from buildPages
     * @return FormField[]
     */
    private function fieldsFromPages(array $pages): array
    {
        $out = [];
        foreach ($pages as $page) {
            foreach ($page['items'] ?? [] as $field) {
                if ($field instanceof FormField) {
                    $out[] = $field;
                }
            }
            $break = $page['break'] ?? null;
            if ($break instanceof FormField) {
                $out[] = $break;
            }
        }
        return $out;
    }

    /**
     * Resolve next page index after leaving $fromIndex using field logic then branch rules.
     * @param array $pages from buildPages
     * @param array $pageKeyIndex
     * @param array $values fieldId => value
     */
    public function resolveNextPage(array $pages, array $pageKeyIndex, int $fromIndex, array $values, array $allFields = []): ?int
    {
        if (!isset($pages[$fromIndex])) {
            return null;
        }

        if (!$allFields) {
            $allFields = $this->fieldsFromPages($pages);
        }

        $target = $this->leavePageTarget($pages, $pageKeyIndex, $fromIndex, $values, $allFields);
        if ($target['end']) {
            return null;
        }
        if ($target['explicit']) {
            if ($target['index'] !== null && $target['index'] <= $fromIndex) {
                Yii::warning('Thiscovery Forms page cycle detected.', 'thiscovery-forms');
                return $this->skipForward($pages, $pageKeyIndex, $fromIndex + 1, $values, new LogicEngine(), $allFields);
            }
            return $target['index'];
        }
        if ($target['index'] === null) {
            return null;
        }

        return $this->skipForward($pages, $pageKeyIndex, (int)$target['index'], $values, new LogicEngine(), $allFields);
    }

    /**
     * Where the respondent goes on leaving a page, before empty-page skipping.
     *
     * @return array{end:bool,explicit:bool,index:?int}
     */
    private function leavePageTarget(array $pages, array $pageKeyIndex, int $fromIndex, array $values, array $allFields): array
    {
        $action = $this->actionLeaveTarget($pages[$fromIndex], $pageKeyIndex, $values);
        if ($action !== null) {
            return $action;
        }

        $engine = new LogicEngine();
        $nav = $engine->pageNavigation(self::navigationFields($pages[$fromIndex]), $values, $allFields);
        if ($nav) {
            if ($nav['action'] === LogicEngine::ACTION_GOTO_END || $nav['action'] === LogicEngine::ACTION_SCREEN_OUT) {
                return ['end' => true, 'explicit' => true, 'index' => null, 'screen_out' => $nav['action'] === LogicEngine::ACTION_SCREEN_OUT];
            }
            if ($nav['action'] === LogicEngine::ACTION_GOTO_PAGE) {
                $goto = (string)$nav['gotoPageKey'];
                if ($goto !== '' && isset($pageKeyIndex[$goto])) {
                    return ['end' => false, 'explicit' => true, 'index' => (int)$pageKeyIndex[$goto]];
                }
                if ($goto !== '') {
                    return ['end' => true, 'explicit' => true, 'index' => null];
                }
            }
        }

        $break = $pages[$fromIndex]['break'] ?? null;
        if ($break instanceof FormField) {
            $cfg = $break->getPageBreakConfig();
            foreach ($cfg['branches'] as $branch) {
                if (FormField::evaluateBranch($branch, $values, [], $allFields)) {
                    $goto = (string)($branch['gotoPageKey'] ?? '');
                    if ($goto !== '' && isset($pageKeyIndex[$goto])) {
                        return ['end' => false, 'explicit' => true, 'index' => (int)$pageKeyIndex[$goto]];
                    }
                    if ($goto !== '') {
                        return ['end' => true, 'explicit' => true, 'index' => null];
                    }
                }
            }
        }

        $next = $fromIndex + 1;
        return [
            'end' => !isset($pages[$next]),
            'explicit' => false,
            'index' => isset($pages[$next]) ? $next : null,
        ];
    }

    /**
     * Go-to actions have no conditions. An answered question's action wins over
     * the page break. A page-break action is unconditional. The last go-to in
     * the action list is the one that applies.
     *
     * @return array{end:bool,explicit:bool,index:?int}|null
     */
    private function actionLeaveTarget(array $page, array $pageKeyIndex, array $values): ?array
    {
        $chosen = null;
        foreach ($page['items'] ?? [] as $field) {
            if (!$field instanceof FormField || !$this->fieldHasAnswer($values, (int)$field->id)) {
                continue;
            }
            $goto = $this->gotoFromActions($field->getActions(), $pageKeyIndex);
            if ($goto !== null) {
                $chosen = $goto;
            }
        }
        if ($chosen !== null) {
            return $chosen;
        }
        $break = $page['break'] ?? null;
        if ($break instanceof FormField) {
            return $this->gotoFromActions($break->getActions(), $pageKeyIndex);
        }
        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array{end:bool,explicit:bool,index:?int}|null
     */
    private function gotoFromActions(array $actions, array $pageKeyIndex): ?array
    {
        $chosen = null;
        foreach ($actions as $action) {
            $fn = (string)($action['fn'] ?? '');
            if ($fn === FormActionService::FN_GOTO_END) {
                $chosen = ['end' => true, 'explicit' => true, 'index' => null];
                continue;
            }
            if ($fn !== FormActionService::FN_GOTO_PAGE) {
                continue;
            }
            $key = trim((string)($action['page_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            if (!isset($pageKeyIndex[$key])) {
                $chosen = ['end' => true, 'explicit' => true, 'index' => null];
                continue;
            }
            $chosen = ['end' => false, 'explicit' => true, 'index' => (int)$pageKeyIndex[$key]];
        }
        return $chosen;
    }

    private function fieldHasAnswer(array $values, int $fieldId): bool
    {
        $value = $values[$fieldId] ?? ($values[(string)$fieldId] ?? null);
        if ($value === null || $value === '' || $value === []) {
            return false;
        }
        return true;
    }

    private function pageMarkedSkip(array $pages, int $idx, array $values, LogicEngine $engine, array $allFields): bool
    {
        $intro = $idx > 0 ? ($pages[$idx - 1]['break'] ?? null) : null;
        if ($intro instanceof FormField) {
            $logic = $intro->getLogic();
            if (($logic['action'] ?? '') === LogicEngine::ACTION_SKIP_PAGE && !empty($logic['rules']) && $engine->rulesMet($logic, $values, $allFields)) {
                return true;
            }
        }
        return $engine->shouldSkipPage($pages[$idx]['items'] ?? [], $values, $allFields);
    }

    private function skipForward(array $pages, array $pageKeyIndex, int $idx, array $values, LogicEngine $engine, array $allFields = []): ?int
    {
        $seen = [];
        $limit = count($pages) + 1;
        $guard = 0;
        while (isset($pages[$idx]) && $guard++ < $limit) {
            if (isset($seen[$idx])) {
                Yii::warning('Thiscovery Forms page cycle detected.', 'thiscovery-forms');
                return null;
            }
            $seen[$idx] = true;
            if (!$this->pageMarkedSkip($pages, $idx, $values, $engine, $allFields)) {
                return $idx;
            }
            $target = $this->leavePageTarget($pages, $pageKeyIndex, $idx, $values, $allFields);
            if ($target['end']) {
                return null;
            }
            if ($target['explicit']) {
                return $target['index'];
            }
            if ($target['index'] === null) {
                return null;
            }
            $idx = (int)$target['index'];
        }
        return null;
    }

    /**
     * Reorder each contiguous run of pages named by a block, then rebuild indexes.
     *
     * @param array{pages:array,pageKeyIndex:array} $built
     * @param array<string, string[]> $blockOrders
     * @return array{pages:array,pageKeyIndex:array}
     */
    public function applyBlockOrder(array $built, array $blockOrders): array
    {
        $pages = array_values($built['pages'] ?? []);
        foreach ($blockOrders as $keys) {
            if (!is_array($keys) || count($keys) < 2) {
                continue;
            }
            $positions = [];
            foreach ($pages as $i => $page) {
                if (in_array((string)($page['pageKey'] ?? ''), $keys, true)) {
                    $positions[] = $i;
                }
            }
            if (count($positions) < 2) {
                continue;
            }
            $start = $positions[0];
            $contiguous = true;
            foreach ($positions as $n => $pos) {
                if ($pos !== $start + $n) {
                    $contiguous = false;
                    break;
                }
            }
            if (!$contiguous) {
                continue;
            }
            $byKey = [];
            foreach ($positions as $pos) {
                $byKey[(string)$pages[$pos]['pageKey']] = $pages[$pos];
            }
            $ordered = [];
            foreach ($keys as $key) {
                if (isset($byKey[(string)$key])) {
                    $ordered[] = $byKey[(string)$key];
                }
            }
            if (count($ordered) !== count($positions)) {
                continue;
            }
            array_splice($pages, $start, count($positions), $ordered);
        }
        $pageKeyIndex = [];
        foreach ($pages as $i => $page) {
            $pages[$i]['index'] = $i;
            $pageKeyIndex[(string)$page['pageKey']] = $i;
        }
        return ['pages' => $pages, 'pageKeyIndex' => $pageKeyIndex];
    }
}
