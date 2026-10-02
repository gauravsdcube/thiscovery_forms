<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use Yii;

/**
 * A question group with options_json.loop repeats. One repeating group may
 * contain one other. A third level is refused. A roster is a list the person
 * adds to, with server keys. Flag
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
        if (!in_array($source, ['fixed', 'choices', 'number', 'roster'], true)) {
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
            'label_field' => trim((string)($loop['label_field'] ?? '')),
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
        if ($cfg['source'] === 'roster') {
            $answer = RandomisationService::$current;
            if (!$answer || !$answer->id) {
                return [];
            }
            foreach ($this->shownRosterKeys($answer, $group, $parentKey) as $key) {
                $full = $parentKey === '' ? $key : $parentKey . '/' . $key;
                $name = $this->rosterName($group, $full, $values, $fields);
                $list[] = [
                    'code' => $key,
                    'label' => $name !== '' ? $name : (trim((string)$group->label) ?: $key),
                    'name' => $name,
                ];
            }
            return array_values($list);
        }
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
                    if (!in_array($cfg['source'], ['fixed', 'roster'], true) && $cfg['field_key'] === '') {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” needs a source question.', ['label' => $label]);
                    }
                    if (in_array($cfg['source'], ['choices', 'number', 'roster'], true) && $cfg['max'] < 1) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” needs a maximum number of repeats.', ['label' => $label]);
                    }
                    if ($cfg['source'] === 'roster' && $cfg['min'] > $cfg['max']) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” cannot require more rows than the maximum.', ['label' => $label]);
                    }
                    if ($cfg['source'] === 'roster' && $cfg['label_field'] !== '' && !$this->labelFieldInside($fields, $index, $depth, $cfg['label_field'])) {
                        $errors[] = Yii::t('ThiscoveryFormsModule.base', '“{label}” uses a name question that is not in the group.', ['label' => $label]);
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
        if ($cfg['source'] === 'roster') {
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
        $engine = new RandomisationEngine();
        $seed = $answer && $answer->id
            ? (new RandomisationService())->seedForAnswer($answer, 'loop:' . $key)
            : $engine->seedInt('preview', 'loop:' . $key);
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
            $rosterCfg = $this->config($chunk['loop']);
            $isRoster = $rosterCfg && $rosterCfg['source'] === 'roster';
            $instances = $this->instances($chunk['loop'], $values, $fields, $parentKey);
            if ($instances === []) {
                if ($isRoster && count($trail) < 2 && $rosterCfg['max'] >= 1) {
                    $key = $first ? $pageKey : ($pageKey . '_c' . $cursor);
                    $row = $this->pageRow($cursor, $key, $first ? $title : '', $first ? $break : null, [], $trail);
                    $row['roster'] = $this->rosterControls($chunk['loop'], $rosterCfg, $parentKey, '', 0, 0, true);
                    $pages[] = $row;
                    $index[$key] = $cursor;
                    $cursor++;
                    $first = false;
                }
                continue;
            }
            $count = count($instances);
            foreach ($instances as $position => $instance) {
                $segment = (string)$instance['code'];
                $full = $parentKey === '' ? $segment : $parentKey . '/' . $segment;
                $nextTrail = $trail;
                $heading = $isRoster
                    ? $this->rosterHeading($chunk['loop'], (string)($instance['name'] ?? ''), $position + 1, $count)
                    : '';
                $nextTrail[] = [
                    'key' => $full,
                    'code' => $segment,
                    'label' => (string)$instance['label'],
                    'index' => $position + 1,
                    'count' => $count,
                    'heading' => $heading,
                    'roster' => $isRoster ? $this->rosterControls($chunk['loop'], $rosterCfg, $parentKey, $full, $position + 1, $count, $position === $count - 1) : null,
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
            'instanceHeading' => $this->trailHasHeading($trail) || count($trail) > 1 ? $this->heading($trail) : '',
            'instanceParent' => count($trail) > 1 ? $trail[count($trail) - 2] : null,
            'roster' => is_array($current) ? ($current['roster'] ?? null) : null,
        ];
    }

    /**
     * @param array<int, array{label:string,index:int,count:int}> $trail
     */
    private function heading(array $trail): string
    {
        $parts = [];
        foreach ($trail as $level) {
            if (!empty($level['heading'])) {
                $parts[] = (string)$level['heading'];
                continue;
            }
            $parts[] = $level['label'] . ', ' . $level['index'] . ' of ' . $level['count'];
        }
        return implode(' — ', $parts);
    }

    /**
     * @param array<int, array<string,mixed>> $trail
     */
    private function trailHasHeading(array $trail): bool
    {
        foreach ($trail as $level) {
            if (!empty($level['heading'])) {
                return true;
            }
        }
        return false;
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

    public function groupKey(FormField $group): string
    {
        $key = trim((string)$group->variable);
        return $key !== '' ? $key : ('group' . (int)$group->id);
    }

    public static function rosterColumnReady(): bool
    {
        $schema = Yii::$app->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        return $schema && isset($schema->columns['roster_json']);
    }

    /**
     * @return string[]
     */
    public function shownRosterKeys(FormAnswer $answer, FormField $group, string $parentKey = ''): array
    {
        $bucket = $this->registryBucket($answer, $group);
        $keys = $bucket['shown'][$parentKey] ?? [];
        if (!is_array($keys)) {
            return [];
        }
        $out = [];
        foreach ($keys as $key) {
            $key = (string)$key;
            if (preg_match('/^r[a-f0-9]{8}$/', $key)) {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * @return string[] Full instance paths, parent/segment when the roster is nested.
     */
    public function rosterInstanceKeys(FormAnswer $answer, FormField $group, bool $hidden = false): array
    {
        $bucket = $this->registryBucket($answer, $group);
        $side = $hidden ? 'hidden' : 'shown';
        $out = [];
        foreach ($bucket[$side] as $parent => $keys) {
            if (!is_array($keys)) {
                continue;
            }
            $parent = (string)$parent;
            foreach ($keys as $key) {
                $key = (string)$key;
                if (!preg_match('/^r[a-f0-9]{8}$/', $key)) {
                    continue;
                }
                $out[] = $parent === '' ? $key : $parent . '/' . $key;
            }
        }
        return $out;
    }

    public function addRow(FormAnswer $answer, FormField $group, string $parentKey = ''): ?string
    {
        $cfg = $this->config($group);
        if (!$cfg || $cfg['source'] !== 'roster' || !self::rosterColumnReady() || $cfg['max'] < 1) {
            return null;
        }
        $registry = $this->readRegistry($answer);
        $bucket = $this->bucket($registry, $this->groupKey($group));
        $shown = $bucket['shown'][$parentKey] ?? [];
        if (!is_array($shown)) {
            $shown = [];
        }
        if (count($shown) >= $cfg['max']) {
            return null;
        }
        $key = $this->freshRosterKey($bucket);
        $shown[] = $key;
        $bucket['shown'][$parentKey] = array_values($shown);
        $registry[$this->groupKey($group)] = $bucket;
        $this->writeRegistry($answer, $registry);
        return $key;
    }

    public function hideRow(FormAnswer $answer, FormField $group, string $parentKey, string $segment): bool
    {
        $cfg = $this->config($group);
        if (!$cfg || $cfg['source'] !== 'roster' || !self::rosterColumnReady()) {
            return false;
        }
        if (!preg_match('/^r[a-f0-9]{8}$/', $segment)) {
            return false;
        }
        $registry = $this->readRegistry($answer);
        $name = $this->groupKey($group);
        $bucket = $this->bucket($registry, $name);
        $shown = array_values(array_map('strval', is_array($bucket['shown'][$parentKey] ?? null) ? $bucket['shown'][$parentKey] : []));
        if (count($shown) <= $cfg['min'] || !in_array($segment, $shown, true)) {
            return false;
        }
        $bucket['shown'][$parentKey] = array_values(array_filter($shown, static fn($key) => $key !== $segment));
        $hidden = is_array($bucket['hidden'][$parentKey] ?? null) ? $bucket['hidden'][$parentKey] : [];
        $hidden[] = $segment;
        $bucket['hidden'][$parentKey] = array_values(array_unique($hidden));
        $registry[$name] = $bucket;
        $this->writeRegistry($answer, $registry);
        return true;
    }

    /**
     * Apply an add or remove posted with the fill. Returns the instance key to reopen.
     */
    public function applyRosterCommands(FormAnswer $answer, CustomForm $form): ?string
    {
        if (!self::active($form) || !self::rosterColumnReady()) {
            return null;
        }
        if (!method_exists(Yii::$app->request, 'post')) {
            return null;
        }
        $add = trim((string)Yii::$app->request->post('roster_add', ''));
        $parent = trim((string)Yii::$app->request->post('roster_parent', ''));
        $remove = trim((string)Yii::$app->request->post('roster_remove', ''));
        $changed = false;
        $open = '';
        $fields = array_values($form->fields);
        if ($add !== '' && $remove === '') {
            $group = $this->rosterGroup($fields, $add);
            if ($group && $this->parentAllowed($group, $fields, $parent)) {
                $key = $this->addRow($answer, $group, $parent);
                if ($key !== null) {
                    $changed = true;
                    $open = $parent === '' ? $key : $parent . '/' . $key;
                }
            }
        }
        if ($remove !== '') {
            $parsed = $this->parseRosterKey($remove);
            $group = $parsed ? $this->rosterGroupForKey($answer, $fields, $parsed['parent'], $parsed['segment']) : null;
            if ($group && $this->hideRow($answer, $group, $parsed['parent'], $parsed['segment'])) {
                $changed = true;
                $left = $this->shownRosterKeys($answer, $group, $parsed['parent']);
                $last = $left ? $left[count($left) - 1] : '';
                $open = $last === '' ? '' : ($parsed['parent'] === '' ? $last : $parsed['parent'] . '/' . $last);
            }
        }
        foreach ($fields as $field) {
            $cfg = $this->config($field);
            if (!$cfg || $cfg['source'] !== 'roster' || $cfg['min'] < 1) {
                continue;
            }
            $parents = $this->rosterParents($field, $fields);
            foreach ($parents as $parentKey) {
                while (count($this->shownRosterKeys($answer, $field, $parentKey)) < $cfg['min']) {
                    $key = $this->addRow($answer, $field, $parentKey);
                    if ($key === null) {
                        break;
                    }
                    $changed = true;
                    if ($open === '' && $add === '' && $remove === '') {
                        $open = $parentKey === '' ? $key : $parentKey . '/' . $key;
                    }
                }
            }
        }
        return $changed ? $open : null;
    }

    /**
     * @param FormField[] $fields
     */
    public function rosterBelowMinimum(CustomForm $form, ?FormAnswer $answer, array $fields): bool
    {
        if (!self::active($form)) {
            return false;
        }
        foreach ($fields as $field) {
            $cfg = $this->config($field);
            if (!$cfg || $cfg['source'] !== 'roster' || $cfg['min'] < 1) {
                continue;
            }
            $parents = $this->rosterParents($field, $fields);
            foreach ($parents as $parentKey) {
                $count = $answer ? count($this->shownRosterKeys($answer, $field, $parentKey)) : 0;
                if ($count < $cfg['min']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $values
     * @param FormField[] $fields
     */
    public function rosterName(FormField $group, string $fullKey, array $values, array $fields): string
    {
        $field = $this->rosterNameField($group, $fields);
        if (!$field) {
            return '';
        }
        $raw = $values[(int)$field->id] ?? null;
        if (!is_array($raw)) {
            return '';
        }
        $value = $raw[$fullKey] ?? '';
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * @param FormField[] $fields
     */
    public function rosterNameField(FormField $group, array $fields): ?FormField
    {
        $cfg = $this->config($group);
        if (!$cfg || $cfg['source'] !== 'roster') {
            return null;
        }
        if ($cfg['label_field'] !== '') {
            $named = $this->fieldByKey($fields, $cfg['label_field']);
            if ($named) {
                return $named;
            }
        }
        $depth = 0;
        $started = false;
        $startDepth = 0;
        foreach ($fields as $field) {
            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                $depth++;
                if ((int)$field->id === (int)$group->id) {
                    $started = true;
                    $startDepth = $depth;
                }
                continue;
            }
            if ($field->type === FormField::TYPE_GROUP_END && $depth > 0) {
                if ($started && $depth === $startDepth) {
                    return null;
                }
                $depth--;
                continue;
            }
            if ($started && $depth === $startDepth && in_array($field->type, [FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA], true)) {
                return $field;
            }
        }
        return null;
    }

    private function rosterHeading(FormField $group, string $name, int $index, int $count): string
    {
        $label = trim((string)$group->label) ?: Yii::t('ThiscoveryFormsModule.base', 'Row');
        $base = $label . ' ' . $index . ' of ' . $count;
        return $name !== '' ? $base . ': ' . $name : $base;
    }

    /**
     * @param array{max:int,min:int} $cfg
     * @return array{variable:string,parent:string,key:string,canAdd:bool,canRemove:bool,addLabel:string,removeLabel:string,confirm:string}
     */
    private function rosterControls(FormField $group, array $cfg, string $parentKey, string $fullKey, int $index, int $count, bool $canAdd): array
    {
        $label = trim((string)$group->label) ?: Yii::t('ThiscoveryFormsModule.base', 'Row');
        $removeLabel = $index > 0
            ? Yii::t('ThiscoveryFormsModule.base', 'Remove {label} {index}', ['label' => $label, 'index' => $index])
            : '';
        return [
            'variable' => $this->groupKey($group),
            'parent' => $parentKey,
            'key' => $fullKey,
            'canAdd' => $canAdd && $count < $cfg['max'],
            'canRemove' => $fullKey !== '' && $count > $cfg['min'],
            'addLabel' => Yii::t('ThiscoveryFormsModule.base', 'Add another'),
            'removeLabel' => $removeLabel,
            'confirm' => $removeLabel !== ''
                ? Yii::t('ThiscoveryFormsModule.base', 'Remove {label} {index}? These answers are kept but not shown.', ['label' => $label, 'index' => $index])
                : '',
        ];
    }

    /**
     * @param FormField[] $fields
     */
    private function labelFieldInside(array $fields, int $groupIndex, int $groupDepth, string $variable): bool
    {
        $depth = $groupDepth;
        for ($i = $groupIndex + 1; $i < count($fields); $i++) {
            $field = $fields[$i];
            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                $depth++;
                continue;
            }
            if ($field->type === FormField::TYPE_GROUP_END && $depth > 0) {
                $depth--;
                if ($depth < $groupDepth) {
                    return false;
                }
                continue;
            }
            if (strcasecmp(trim((string)$field->variable), $variable) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{shown:array<string,array<int,string>>,hidden:array<string,array<int,string>>}
     */
    private function readRegistry(FormAnswer $answer): array
    {
        if (!self::rosterColumnReady()) {
            return [];
        }
        $decoded = json_decode((string)$answer->roster_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $registry
     */
    private function writeRegistry(FormAnswer $answer, array $registry): void
    {
        $answer->roster_json = $registry ? json_encode($registry, JSON_UNESCAPED_UNICODE) : '';
        $answer->save(false, ['roster_json', 'updated_at']);
    }

    /**
     * @param array<string,mixed> $registry
     * @return array{shown:array<string,array<int,string>>,hidden:array<string,array<int,string>>}
     */
    private function bucket(array $registry, string $groupKey): array
    {
        $bucket = $registry[$groupKey] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }
        return [
            'shown' => is_array($bucket['shown'] ?? null) ? $bucket['shown'] : [],
            'hidden' => is_array($bucket['hidden'] ?? null) ? $bucket['hidden'] : [],
        ];
    }

    /**
     * @return array{shown:array<string,array<int,string>>,hidden:array<string,array<int,string>>}
     */
    private function registryBucket(FormAnswer $answer, FormField $group): array
    {
        return $this->bucket($this->readRegistry($answer), $this->groupKey($group));
    }

    /**
     * @param array{shown:array,hidden:array} $bucket
     */
    private function freshRosterKey(array $bucket): string
    {
        $used = [];
        foreach (['shown', 'hidden'] as $side) {
            foreach ($bucket[$side] as $keys) {
                if (!is_array($keys)) {
                    continue;
                }
                foreach ($keys as $key) {
                    $used[(string)$key] = true;
                }
            }
        }
        do {
            $key = 'r' . bin2hex(random_bytes(4));
        } while (isset($used[$key]));
        return $key;
    }

    /**
     * @param FormField[] $fields
     */
    private function rosterGroup(array $fields, string $variable): ?FormField
    {
        foreach ($fields as $field) {
            if ($field->type !== FormField::TYPE_QUESTION_GROUP) {
                continue;
            }
            $cfg = $this->config($field);
            if ($cfg && $cfg['source'] === 'roster' && strcasecmp($this->groupKey($field), $variable) === 0) {
                return $field;
            }
        }
        return null;
    }

    /**
     * @param FormField[] $fields
     */
    private function rosterGroupForKey(FormAnswer $answer, array $fields, string $parentKey, string $segment): ?FormField
    {
        foreach ($fields as $field) {
            $cfg = $this->config($field);
            if (!$cfg || $cfg['source'] !== 'roster') {
                continue;
            }
            if (in_array($segment, $this->shownRosterKeys($answer, $field, $parentKey), true)) {
                return $field;
            }
        }
        return null;
    }

    /**
     * @return array{parent:string,segment:string}|null
     */
    private function parseRosterKey(string $full): ?array
    {
        $full = trim($full);
        if ($full === '') {
            return null;
        }
        $parent = '';
        $segment = $full;
        $slash = strrpos($full, '/');
        if ($slash !== false) {
            $parent = substr($full, 0, $slash);
            $segment = substr($full, $slash + 1);
        }
        if (!preg_match('/^r[a-f0-9]{8}$/', $segment)) {
            return null;
        }
        return ['parent' => $parent, 'segment' => $segment];
    }

    /**
     * @param FormField[] $fields
     */
    private function parentAllowed(FormField $group, array $fields, string $parentKey): bool
    {
        $parent = $this->enclosingLoop($group, $fields);
        if (!$parent) {
            return $parentKey === '';
        }
        if ($parentKey === '') {
            return false;
        }
        $answer = RandomisationService::$current;
        if (!$answer) {
            return false;
        }
        $outerParent = '';
        $segment = $parentKey;
        $slash = strrpos($parentKey, '/');
        if ($slash !== false) {
            $outerParent = substr($parentKey, 0, $slash);
            $segment = substr($parentKey, $slash + 1);
        }
        foreach ($this->instances($parent, $answer->getValuesMap(), $fields, $outerParent) as $instance) {
            if ((string)$instance['code'] === $segment) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parents that should already have the minimum number of roster rows.
     *
     * @param FormField[] $fields
     * @return string[]
     */
    private function rosterParents(FormField $group, array $fields): array
    {
        $parent = $this->enclosingLoop($group, $fields);
        if (!$parent) {
            return [''];
        }
        $answer = RandomisationService::$current;
        if (!$answer) {
            return [];
        }
        $out = [];
        foreach ($this->instances($parent, $answer->getValuesMap(), $fields, '') as $instance) {
            $out[] = (string)$instance['code'];
        }
        return $out;
    }

    /**
     * @param FormField[] $fields
     */
    private function enclosingLoop(FormField $group, array $fields): ?FormField
    {
        $stack = [];
        foreach ($fields as $field) {
            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                if ((int)$field->id === (int)$group->id) {
                    for ($i = count($stack) - 1; $i >= 0; $i--) {
                        if ($this->config($stack[$i])) {
                            return $stack[$i];
                        }
                    }
                    return null;
                }
                $stack[] = $field;
                continue;
            }
            if ($field->type === FormField::TYPE_GROUP_END && $stack) {
                array_pop($stack);
            }
        }
        return null;
    }
}
