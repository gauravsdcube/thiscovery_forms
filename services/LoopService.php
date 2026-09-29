<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use Yii;

/**
 * A question group with options_json.loop repeats. One repeating group may
 * contain one other. A third level is refused. Rosters are not built. Flag
 * off keeps instance_key blank and shows the group once.
 */
class LoopService
{
    /** @var array{label:string,index:int,count:int,key:string,parent?:array{label:string,index:int,count:int,key:string}}|null */
    public static $pipe = null;

    public static function active(CustomForm $form): bool
    {
        return Module::loopsEnabled() && self::formEnabled($form) && self::columnReady();
    }

    public static function formEnabled(CustomForm $form): bool
    {
        $value = (string)$form->getSetting('loops_enabled', '0');
        return in_array($value, ['1', 'true', 'on'], true);
    }

    public static function columnReady(): bool
    {
        $schema = Yii::$app->db->schema->getTableSchema('{{%custom_form_answer_field}}', true);
        return $schema && isset($schema->columns['instance_key']);
    }

    public function saveFormSettings(CustomForm $form, array $posted): void
    {
        $enabled = $posted['enabled'] ?? '0';
        $form->setSetting('loops_enabled', in_array((string)$enabled, ['1', 'true', 'on'], true) ? '1' : '0');
    }

    /**
     * @return array{source:string,field_key:string,max:int,min:int,items:array<int,array{code:string,label:string}>,randomise:bool,show:?int}|null
     */
    public function config(FormField $field): ?array
    {
        if ($field->type !== FormField::TYPE_QUESTION_GROUP) {
            return null;
        }
        $decoded = json_decode((string)$field->options_json, true);
        $loop = is_array($decoded) ? ($decoded['loop'] ?? null) : null;
        if (!is_array($loop) || empty($loop['source'])) {
            return null;
        }
        $source = (string)$loop['source'];
        if (!in_array($source, ['fixed', 'choices', 'number'], true)) {
            return null;
        }
        $items = [];
        foreach ((array)($loop['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = trim((string)($item['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $items[] = ['code' => $code, 'label' => trim((string)($item['label'] ?? $code))];
        }
        $show = $loop['show'] ?? null;
        return [
            'source' => $source,
            'field_key' => trim((string)($loop['field_key'] ?? '')),
            'max' => max(0, (int)($loop['max'] ?? 0)),
            'min' => max(0, (int)($loop['min'] ?? 0)),
            'items' => $items,
            'randomise' => !empty($loop['randomise']),
            'show' => ($show === null || $show === '') ? null : max(0, (int)$show),
        ];
    }

    /**
     * @param FormField[] $fields
     * @return array<int, true>
     */
    public function loopFieldIds(CustomForm $form, ?array $fields = null): array
    {
        if (!self::active($form)) {
            return [];
        }
        $fields = $fields ?? array_values($form->fields);
        $ids = [];
        $stack = [];
        foreach ($fields as $field) {
            if (!$field instanceof FormField) {
                continue;
            }
            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                $stack[] = $this->config($field) !== null;
                continue;
            }
            if ($field->type === FormField::TYPE_GROUP_END && $stack) {
                array_pop($stack);
                continue;
            }
            if ($field->collectsAnswer() && in_array(true, $stack, true)) {
                $ids[(int)$field->id] = true;
            }
        }
        return $ids;
    }

    public function isLoopField(CustomForm $form, FormField $field): bool
    {
        return isset($this->loopFieldIds($form)[(int)$field->id]);
    }

    /**
     * @param FormField[] $fields
     * @param array<string,mixed> $values
     * @return array<int, array{code:string,label:string}>
     */
    public function instances(FormField $group, array $values, array $fields, string $parentKey = ''): array
    {
        $cfg = $this->config($group);
        if (!$cfg) {
            return [];
        }
        $list = [];
        if ($cfg['source'] === 'fixed') {
            $list = $cfg['items'];
        } elseif ($cfg['source'] === 'choices') {
            $source = $this->fieldByKey($fields, $cfg['field_key']);
            if (!$source) {
                return [];
            }
            $raw = $this->sourceRaw($source, $values, $parentKey);
            if ($raw === null || $raw === '' || $raw === []) {
                return [];
            }
            $selected = is_array($raw) ? $raw : [$raw];
            foreach ($source->getChoicePairs() as $pair) {
                $code = (string)$pair['code'];
                $label = (string)$pair['label'];
                foreach ($selected as $item) {
                    $item = is_scalar($item) ? (string)$item : '';
                    if ($item !== '' && ($item === $code || strcasecmp($item, $label) === 0)) {
                        $list[] = ['code' => $code !== '' ? $code : $label, 'label' => $label];
                    }
                }
            }
            if ($cfg['max'] > 0) {
                $list = array_slice($list, 0, $cfg['max']);
            }
        } else {
            $source = $this->fieldByKey($fields, $cfg['field_key']);
            if (!$source || $cfg['max'] < 1) {
                return [];
            }
            $raw = $this->sourceRaw($source, $values, $parentKey);
            if ($raw === null || $raw === '' || !is_numeric($raw)) {
                return [];
            }
            $count = max(0, min($cfg['max'], (int)$raw));
            for ($i = 1; $i <= $count; $i++) {
                $list[] = ['code' => 'n' . $i, 'label' => (string)$i];
            }
        }
        $list = array_values(array_filter($list, static function ($item) {
            return is_array($item) && !str_contains((string)($item['code'] ?? ''), '/');
        }));
        if ($cfg['randomise'] && count($list) > 1) {
            $list = $this->ordered($group, $list, $parentKey);
        }
        if ($cfg['show'] !== null && $cfg['show'] > 0) {
            $list = array_slice($list, 0, $cfg['show']);
        }
        return array_values($list);
    }

    /**
     * @return array<int, string>
     */
    public function authoringErrors(CustomForm $form): array
    {
        if (!self::formEnabled($form)) {
            return [];
        }
        $errors = [];
        $fields = array_values($form->getFields()->all());
        $depth = 0;
        $loopDepth = 0;
        $deepest = 0;
        $loopGroups = [];
        $seen = [];
        foreach ($fields as $index => $field) {
            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                $depth++;
                $cfg = $this->config($field);
                if ($cfg) {
                    if ($loopDepth > 1) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', 'A loop can contain only one nested loop.');
                    }
                    $loopDepth++;
                    $deepest = max($deepest, $loopDepth);
                    $loopGroups[] = $field;
                    $label = trim((string)$field->label) ?: Yii::t('ThiscoveryFormsModule.base', 'Question group');
                    if ($cfg['source'] !== 'fixed' && $cfg['field_key'] === '') {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” needs a source question.', ['label' => $label]);
                    }
                    if (in_array($cfg['source'], ['choices', 'number'], true) && $cfg['max'] < 1) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” needs a maximum number of repeats.', ['label' => $label]);
                    }
                    if ($cfg['source'] === 'fixed' && $cfg['items'] === []) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” needs at least one fixed item.', ['label' => $label]);
                    }
                    if ($cfg['field_key'] !== '') {
                        $sourceIndex = null;
                        foreach ($fields as $i => $candidate) {
                            if ((string)$candidate->id === $cfg['field_key'] || strcasecmp(trim((string)$candidate->variable), $cfg['field_key']) === 0) {
                                $sourceIndex = $i;
                                break;
                            }
                        }
                        if ($sourceIndex === null) {
                            $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” uses question “{key}”, which is not on this form.', [
                                'label' => $label,
                                'key' => $cfg['field_key'],
                            ]);
                        } elseif ($sourceIndex >= $index) {
                            $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” must use a question that comes before the group.', [
                                'label' => $label,
                            ]);
                        }
                    }
                }
                $seen[] = $cfg ? 1 : 0;
                continue;
            }
            if ($field->type === FormField::TYPE_GROUP_END && $depth > 0) {
                $depth--;
                $wasLoop = array_pop($seen);
                if ($wasLoop) {
                    $loopDepth = max(0, $loopDepth - 1);
                }
            }
        }
        foreach ($fields as $field) {
            $raw = json_decode((string)$field->logic_json, true);
            if (!is_array($raw)) {
                continue;
            }
            foreach ($this->rawLeaves($raw['rules'] ?? []) as $leaf) {
                $aggregate = trim((string)($leaf['aggregate'] ?? ''));
                if ($aggregate !== '' && !in_array($aggregate, ['any', 'all', 'count', 'sum'], true)) {
                    $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” uses an unknown loop aggregate “{aggregate}”.', [
                        'label' => trim((string)$field->label) ?: Yii::t('ThiscoveryFormsModule.base', 'Question'),
                        'aggregate' => $aggregate,
                    ]);
                }
            }
        }
        if ($deepest > 1) {
            foreach ($loopGroups as $group) {
                $errors = array_merge($errors, $this->nestedCodeErrors($group, $fields));
            }
        }
        return $errors;
    }

    /**
     * A nested path is parent/child and must fit the instance key.
     *
     * @param FormField[] $fields
     * @return array<int, string>
     */
    private function nestedCodeErrors(FormField $group, array $fields): array
    {
        $cfg = $this->config($group);
        if (!$cfg) {
            return [];
        }
        $label = trim((string)$group->label) ?: Yii::t('ThiscoveryFormsModule.base', 'Question group');
        $codes = [];
        if ($cfg['source'] === 'fixed') {
            foreach ($cfg['items'] as $item) {
                $codes[] = (string)$item['code'];
            }
        } elseif ($cfg['source'] === 'choices') {
            $source = $this->fieldByKey($fields, $cfg['field_key']);
            if ($source) {
                foreach ($source->getChoicePairs() as $pair) {
                    if ((string)$pair['code'] !== '') {
                        $codes[] = (string)$pair['code'];
                    }
                }
            }
        }
        $errors = [];
        foreach ($codes as $code) {
            if (str_contains($code, '/')) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” uses repeat code “{code}”, which cannot contain a slash.', [
                    'label' => $label,
                    'code' => $code,
                ]);
            } elseif (strlen($code) > 90) {
                $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” uses repeat code “{code}”, which is too long to nest.', [
                    'label' => $label,
                    'code' => $code,
                ]);
            }
        }
        return $errors;
    }

    /**
     * @param mixed $rules
     * @return array<int, array<string,mixed>>
     */
    private function rawLeaves($rules): array
    {
        if (!is_array($rules)) {
            return [];
        }
        $out = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (isset($rule['all']) || isset($rule['any'])) {
                $out = array_merge($out, $this->rawLeaves($rule['all'] ?? $rule['any'] ?? []));
                continue;
            }
            if (isset($rule['rules']) && is_array($rule['rules'])) {
                $out = array_merge($out, $this->rawLeaves($rule['rules']));
                continue;
            }
            $out[] = $rule;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $built
     * @param FormField[] $fields
     * @param array<string,mixed> $values
     * @return array{pages:array,pageKeyIndex:array}
     */
    public function expandPages(array $built, CustomForm $form, array $fields, array $values): array
    {
        if (!self::active($form)) {
            return $built;
        }
        $pages = [];
        $index = [];
        $cursor = 0;
        foreach ($built['pages'] as $page) {
            $this->emitChunks(
                $this->splitPage(array_values($page['items'] ?? [])),
                (string)$page['pageKey'],
                (string)$page['title'],
                $page['break'] ?? null,
                [],
                $values,
                $fields,
                $pages,
                $index,
                $cursor,
                true
            );
        }
        if ($pages === []) {
            return $built;
        }
        return ['pages' => $pages, 'pageKeyIndex' => $index];
    }

    /**
     * @param array<int, true> $shownIds
     */
    public function shownQuestionCount(CustomForm $form, array $values, array $shownIds): int
    {
        $count = count($shownIds);
        if (!self::active($form)) {
            return $count;
        }
        $fields = array_values($form->fields);
        $extra = 0;
        foreach ($fields as $field) {
            if (!$field->collectsAnswer() || !isset($shownIds[(int)$field->id]) || !$this->isLoopField($form, $field)) {
                continue;
            }
            $n = count($this->shownPaths($fields, $field, $values));
            if ($n > 1) {
                $extra += $n - 1;
            }
        }
        return $count + $extra;
    }

    /**
     * Shown instance paths for a question, outer code then inner code.
     *
     * @param FormField[] $fields
     * @param array<string,mixed> $values
     * @return array<int, array{code:string,label:string}>
     */
    public function shownPaths(array $fields, FormField $target, array $values): array
    {
        return $this->expandShown($this->loopStack($fields, $target), '', $values, $fields);
    }

    /**
     * Every possible export path, including repeats that this response did not show.
     *
     * @param FormField[] $fields
     * @return array<int, array{code:string,label:string}>
     */
    public function columnPaths(array $fields, FormField $target): array
    {
        $groups = $this->loopStack($fields, $target);
        if ($groups === []) {
            return [];
        }
        $paths = [['code' => '', 'label' => '']];
        foreach ($groups as $group) {
            $next = [];
            foreach ($paths as $path) {
                foreach ($this->columnsFor($group, $fields) as $column) {
                    $code = (string)$column['code'];
                    if ($code === '' || str_contains($code, '/')) {
                        continue;
                    }
                    $next[] = [
                        'code' => $path['code'] === '' ? $code : $path['code'] . '/' . $code,
                        'label' => $path['label'] === '' ? (string)$column['label'] : $path['label'] . ' — ' . $column['label'],
                    ];
                }
            }
            $paths = $next;
        }
        return $paths;
    }

    public function exportColumn(string $variable, string $code): string
    {
        return $variable . '__' . str_replace('/', '__', $code);
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public function columnsFor(FormField $group, array $fields): array
    {
        $cfg = $this->config($group);
        if (!$cfg) {
            return [];
        }
        if ($cfg['source'] === 'fixed') {
            return $cfg['items'];
        }
        if ($cfg['source'] === 'number') {
            $out = [];
            for ($i = 1; $i <= max(0, $cfg['max']); $i++) {
                $out[] = ['code' => 'n' . $i, 'label' => (string)$i];
            }
            return $out;
        }
        $source = $this->fieldByKey($fields, $cfg['field_key']);
        if (!$source) {
            return [];
        }
        $out = [];
        foreach ($source->getChoicePairs() as $pair) {
            $code = (string)$pair['code'];
            if ($code === '') {
                continue;
            }
            $out[] = ['code' => $code, 'label' => (string)$pair['label']];
            if ($cfg['max'] > 0 && count($out) >= $cfg['max']) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param FormField[] $fields
     */
    public function groupForField(array $fields, FormField $target): ?FormField
    {
        $stack = [];
        foreach ($fields as $field) {
            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                $stack[] = $field;
                continue;
            }
            if ($field->type === FormField::TYPE_GROUP_END && $stack) {
                array_pop($stack);
                continue;
            }
            if ((int)$field->id === (int)$target->id) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($this->config($stack[$i])) {
                        return $stack[$i];
                    }
                }
                return null;
            }
        }
        return null;
    }

    /**
     * @param array<int, array{code:string,label:string}> $list
     * @return array<int, array{code:string,label:string}>
     */
    private function ordered(FormField $group, array $list, string $parentKey = ''): array
    {
        $answer = RandomisationService::$current;
        $key = trim((string)$group->variable) ?: ('group' . (int)$group->id);
        if ($parentKey !== '') {
            $key .= '/' . $parentKey;
        }
        if ($answer && $answer->id) {
            $orders = (new RandomisationService())->orders($answer);
            $stored = $orders['loops'][$key] ?? null;
            if (is_array($stored) && $stored) {
                $byCode = [];
                foreach ($list as $item) {
                    $byCode[$item['code']] = $item;
                }
                $ordered = [];
                foreach ($stored as $code) {
                    if (isset($byCode[(string)$code])) {
                        $ordered[] = $byCode[(string)$code];
                        unset($byCode[(string)$code]);
                    }
                }
                foreach ($byCode as $item) {
                    $ordered[] = $item;
                }
                return $ordered;
            }
        }
        $seed = $answer && $answer->id ? crc32((string)$answer->id . ':' . $key) & 0x7fffffff : 1;
        $engine = new RandomisationEngine();
        $codes = array_map(static fn($item) => $item['code'], $list);
        $shuffled = $engine->shuffle($codes, $seed);
        if ($answer && $answer->id) {
            $this->rememberOrder($answer, $key, $shuffled);
        }
        $byCode = [];
        foreach ($list as $item) {
            $byCode[$item['code']] = $item;
        }
        $ordered = [];
        foreach ($shuffled as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code];
            }
        }
        return $ordered;
    }

    /**
     * @param array<int, array{loop:?FormField,items:FormField[]}> $chunks
     * @param array<int, array{key:string,code:string,label:string,index:int,count:int}> $trail
     * @param FormField[] $fields
     * @param array<string,mixed> $values
     * @param array<int, array<string,mixed>> $pages
     * @param array<string, int> $index
     */
    private function emitChunks(
        array $chunks,
        string $pageKey,
        string $title,
        $break,
        array $trail,
        array $values,
        array $fields,
        array &$pages,
        array &$index,
        int &$cursor,
        bool $keepFirst
    ): void {
        $first = $keepFirst;
        foreach ($chunks as $chunk) {
            if ($chunk['loop'] === null || count($trail) >= 2) {
                $key = $first ? $pageKey : ($pageKey . '_c' . $cursor);
                $pages[] = $this->pageRow($cursor, $key, $first ? $title : '', $first ? $break : null, $chunk['items'], $trail);
                $index[$key] = $cursor;
                $cursor++;
                $first = false;
                continue;
            }
            $parentKey = $trail ? (string)$trail[count($trail) - 1]['key'] : '';
            $instances = $this->instances($chunk['loop'], $values, $fields, $parentKey);
            if ($instances === []) {
                continue;
            }
            $count = count($instances);
            foreach ($instances as $position => $instance) {
                $segment = (string)$instance['code'];
                $full = $parentKey === '' ? $segment : $parentKey . '/' . $segment;
                $nextTrail = $trail;
                $nextTrail[] = [
                    'key' => $full,
                    'code' => $segment,
                    'label' => (string)$instance['label'],
                    'index' => $position + 1,
                    'count' => $count,
                ];
                $childKey = $pageKey . '__' . $segment;
                $inner = $this->splitPage($chunk['items']);
                $nested = false;
                foreach ($inner as $part) {
                    if ($part['loop'] !== null) {
                        $nested = true;
                        break;
                    }
                }
                if ($nested) {
                    $this->emitChunks($inner, $childKey, (string)$instance['label'], null, $nextTrail, $values, $fields, $pages, $index, $cursor, true);
                    continue;
                }
                $pages[] = $this->pageRow($cursor, $childKey, (string)$instance['label'], null, $chunk['items'], $nextTrail);
                $index[$childKey] = $cursor;
                $cursor++;
            }
        }
    }

    /**
     * @param FormField[] $items
     * @param array<int, array{key:string,label:string,index:int,count:int}> $trail
     * @return array<string,mixed>
     */
    private function pageRow(int $cursor, string $key, string $title, $break, array $items, array $trail): array
    {
        $current = $trail ? $trail[count($trail) - 1] : null;
        return [
            'index' => $cursor,
            'pageKey' => $key,
            'title' => $title,
            'break' => $break,
            'items' => $items,
            'instanceKey' => $current['key'] ?? '',
            'instanceLabel' => $current['label'] ?? '',
            'instanceIndex' => (int)($current['index'] ?? 0),
            'instanceCount' => (int)($current['count'] ?? 0),
            'instanceHeading' => count($trail) > 1 ? $this->heading($trail) : '',
            'instanceParent' => count($trail) > 1 ? $trail[count($trail) - 2] : null,
        ];
    }

    /**
     * @param array<int, array{label:string,index:int,count:int}> $trail
     */
    private function heading(array $trail): string
    {
        $parts = [];
        foreach ($trail as $level) {
            $parts[] = $level['label'] . ', ' . $level['index'] . ' of ' . $level['count'];
        }
        return implode(' — ', $parts);
    }

    /**
     * @param FormField[] $groups
     * @param FormField[] $fields
     * @param array<string,mixed> $values
     * @return array<int, array{code:string,label:string}>
     */
    private function expandShown(array $groups, string $parentKey, array $values, array $fields): array
    {
        if ($groups === []) {
            return [];
        }
        $group = array_shift($groups);
        $out = [];
        foreach ($this->instances($group, $values, $fields, $parentKey) as $instance) {
            $code = $parentKey === '' ? (string)$instance['code'] : $parentKey . '/' . $instance['code'];
            if ($groups === []) {
                $out[] = ['code' => $code, 'label' => (string)$instance['label']];
                continue;
            }
            foreach ($this->expandShown($groups, $code, $values, $fields) as $child) {
                $out[] = $child;
            }
        }
        return $out;
    }

    /**
     * Enclosing loop groups, outermost first.
     *
     * @param FormField[] $fields
     * @return FormField[]
     */
    private function loopStack(array $fields, FormField $target): array
    {
        $stack = [];
        foreach ($fields as $field) {
            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                $stack[] = $field;
                continue;
            }
            if ($field->type === FormField::TYPE_GROUP_END && $stack) {
                array_pop($stack);
                continue;
            }
            if ((int)$field->id === (int)$target->id) {
                $loops = [];
                foreach ($stack as $group) {
                    if ($this->config($group)) {
                        $loops[] = $group;
                    }
                }
                return $loops;
            }
        }
        return [];
    }

    /**
     * @param array<string,mixed> $values
     * @return mixed
     */
    private function sourceRaw(FormField $source, array $values, string $parentKey)
    {
        $raw = $values[(int)$source->id] ?? $values[(string)$source->variable] ?? null;
        if ($parentKey !== '' && is_array($raw) && !array_is_list($raw) && array_key_exists($parentKey, $raw)) {
            return $raw[$parentKey];
        }
        return $raw;
    }

    /**
     * @param FormField[] $items
     * @return array<int, array{loop:?FormField,items:FormField[]}>
     */
    private function splitPage(array $items): array
    {
        $chunks = [];
        $current = ['loop' => null, 'items' => []];
        $depth = 0;
        $loopAt = 0;
        foreach ($items as $field) {
            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                $depth++;
                if ($depth === 1 && $this->config($field)) {
                    if ($current['items']) {
                        $chunks[] = $current;
                    }
                    $current = ['loop' => $field, 'items' => []];
                    $loopAt = $depth;
                    continue;
                }
            }
            if ($field->type === FormField::TYPE_GROUP_END && $depth > 0) {
                if ($current['loop'] && $depth === $loopAt) {
                    $chunks[] = $current;
                    $current = ['loop' => null, 'items' => []];
                    $loopAt = 0;
                    $depth--;
                    continue;
                }
                $depth--;
            }
            if ($field->type !== FormField::TYPE_QUESTION_GROUP || $current['loop']) {
                if ($field->type !== FormField::TYPE_GROUP_END) {
                    $current['items'][] = $field;
                }
            }
        }
        if ($current['items'] || $current['loop']) {
            $chunks[] = $current;
        }
        return $chunks;
    }

    /**
     * @param string[] $codes
     */
    private function rememberOrder(FormAnswer $answer, string $key, array $codes): void
    {
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_presentation}}', true) === null) {
            return;
        }
        $orders = (new RandomisationService())->orders($answer);
        $orders['loops'][$key] = array_values($codes);
        $json = json_encode([
            'options' => $orders['options'],
            'questions' => $orders['questions'],
            'pages' => $orders['pages'],
            'shown' => $orders['shown'],
            'loops' => $orders['loops'],
        ], JSON_UNESCAPED_UNICODE);
        $exists = (new \yii\db\Query())->from('{{%custom_form_presentation}}')->where(['answer_id' => (int)$answer->id])->exists();
        if ($exists) {
            Yii::$app->db->createCommand()->update('{{%custom_form_presentation}}', [
                'orders_json' => $json,
            ], ['answer_id' => (int)$answer->id])->execute();
            return;
        }
        Yii::$app->db->createCommand()->insert('{{%custom_form_presentation}}', [
            'answer_id' => (int)$answer->id,
            'seed' => bin2hex(random_bytes(16)),
            'orders_json' => $json,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
    }

    /**
     * @param FormField[] $fields
     */
    private function fieldByKey(array $fields, string $key): ?FormField
    {
        foreach ($fields as $field) {
            if ((string)$field->id === $key || strcasecmp(trim((string)$field->variable), $key) === 0) {
                return $field;
            }
        }
        return null;
    }
}
