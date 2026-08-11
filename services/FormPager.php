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
                // Close current page; the break defines navigation out of this page
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
     * Resolve next page index after leaving $fromIndex using branch rules.
     * @param array $pages from buildPages
     * @param array $pageKeyIndex
     * @param array $values fieldId => value
     */
    public function resolveNextPage(array $pages, array $pageKeyIndex, int $fromIndex, array $values): ?int
    {
        if (!isset($pages[$fromIndex])) {
            return null;
        }
        $break = $pages[$fromIndex]['break'] ?? null;
        if ($break instanceof FormField) {
            $cfg = $break->getPageBreakConfig();
            foreach ($cfg['branches'] as $branch) {
                if (FormField::evaluateBranch($branch, $values)) {
                    $goto = (string)($branch['gotoPageKey'] ?? '');
                    if ($goto !== '' && isset($pageKeyIndex[$goto])) {
                        return (int)$pageKeyIndex[$goto];
                    }
                }
            }
        }

        $next = $fromIndex + 1;
        return isset($pages[$next]) ? $next : null;
    }
}
