<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormField;

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

        $engine = new LogicEngine();
        $nav = $engine->pageNavigation(self::navigationFields($pages[$fromIndex]), $values, $allFields);
        if ($nav) {
            if ($nav['action'] === LogicEngine::ACTION_GOTO_END) {
                return null;
            }
            if ($nav['action'] === LogicEngine::ACTION_GOTO_PAGE) {
                $goto = (string)$nav['gotoPageKey'];
                if ($goto !== '' && isset($pageKeyIndex[$goto])) {
                    return (int)$pageKeyIndex[$goto];
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
                        return (int)$pageKeyIndex[$goto];
                    }
                }
            }
        }

        return $this->skipForward($pages, $fromIndex + 1, $values, $engine, $allFields);
    }

    /**
     * Field ids on pages the respondent actually reaches with the current answers.
     * Pages jumped over by go-to-page / go-to-end, or skipped because they have
     * nothing visible, are omitted so their required questions are not validated.
     *
     * @param FormField[] $fields
     * @return array<int, true>
     */
    public function visitedFieldIds(array $fields, array $values): array
    {
        $built = $this->buildPages($fields);
        $pages = $built['pages'];
        $pageKeyIndex = $built['pageKeyIndex'];
        $visited = [];
        $idx = 0;
        $guard = 0;
        while (isset($pages[$idx]) && $guard++ < 80) {
            if (isset($visited[$idx])) {
                break;
            }
            $visited[$idx] = true;
            $next = $this->resolveNextPage($pages, $pageKeyIndex, $idx, $values, $fields);
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

    private function skipForward(array $pages, int $idx, array $values, LogicEngine $engine, array $allFields = []): ?int
    {
        $guard = 0;
        while (isset($pages[$idx]) && $guard++ < 80) {
            $items = $pages[$idx]['items'] ?? [];
            if (!$engine->shouldSkipPage($items, $values, $allFields)) {
                return $idx;
            }
            $idx++;
        }
        return null;
    }
}
