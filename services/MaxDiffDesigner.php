<?php

namespace humhub\modules\thiscoveryForms\services;

/**
 * MaxDiff sets spread around the item list. The score is (best − worst) / times shown.
 */
class MaxDiffDesigner
{
    /**
     * @param string[] $items
     * @return string[][]
     */
    public function generateSets(array $items, int $setSize, int $setCount): array
    {
        $items = array_values(array_filter(array_map('strval', $items), static fn ($item) => $item !== ''));
        $n = count($items);
        if ($n < 2) {
            return [];
        }
        $setSize = max(2, min($setSize, $n));
        $setCount = max(1, $setCount);
        $sets = [];
        $seen = [];

        for ($step = 1; $step < $n && count($sets) < $setCount; $step++) {
            // Spread the first sets' start positions over the list, so 10 items in 5 sets of 4
            // cover items 9 and 10 too (V3-42, SCO-1): then the remaining starts in order.
            $starts = [];
            if ($step === 1) {
                for ($k = 0; $k < $setCount; $k++) {
                    $starts[] = (int)floor($k * $n / $setCount) % $n;
                }
            }
            $starts = array_values(array_unique(array_merge($starts, range(0, $n - 1))));
            foreach ($starts as $start) {
                if (count($sets) >= $setCount) {
                    break;
                }
                $set = [];
                for ($j = 0; $j < $setSize; $j++) {
                    $set[] = $items[($start + ($j * $step)) % $n];
                }
                $set = array_values(array_unique($set));
                if (count($set) < $setSize || isset($seen[$this->setKey($set)])) {
                    continue;
                }
                $seen[$this->setKey($set)] = true;
                $sets[] = $set;
            }
        }

        if (count($sets) < $setCount && $n <= 16) {
            $this->appendCombinations($items, $setSize, $setCount, $sets, $seen);
        }

        if ($sets !== [] && count($sets) < $setCount) {
            $base = $sets;
            $i = 0;
            while (count($sets) < $setCount) {
                $sets[] = $base[$i % count($base)];
                $i++;
            }
        }

        return array_slice($sets, 0, $setCount);
    }

    /**
     * @param string[] $set
     */
    private function setKey(array $set): string
    {
        $copy = $set;
        sort($copy);
        return implode("\0", $copy);
    }

    /**
     * @param string[] $items
     * @param string[][] $sets
     * @param array<string, true> $seen
     */
    private function appendCombinations(array $items, int $setSize, int $setCount, array &$sets, array &$seen): void
    {
        $n = count($items);
        $index = range(0, $setSize - 1);
        while (count($sets) < $setCount) {
            $set = [];
            foreach ($index as $at) {
                $set[] = $items[$at];
            }
            $key = $this->setKey($set);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $sets[] = $set;
            }
            $pos = $setSize - 1;
            while ($pos >= 0 && $index[$pos] === $n - $setSize + $pos) {
                $pos--;
            }
            if ($pos < 0) {
                break;
            }
            $index[$pos]++;
            for ($k = $pos + 1; $k < $setSize; $k++) {
                $index[$k] = $index[$k - 1] + 1;
            }
        }
    }

    /**
     * @param string[] $items
     * @param array $responses list of set answers: [['best' => x, 'worst' => y, 'items' => [...]], ...]
     * @return array<string, array{best:int,worst:int,shown:int,score:float}>
     */
    public function scores(array $items, array $responses): array
    {
        $scores = [];
        foreach ($items as $item) {
            $item = (string)$item;
            $scores[$item] = ['best' => 0, 'worst' => 0, 'shown' => 0, 'score' => 0.0];
        }
        foreach ($responses as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $best = (string)($pair['best'] ?? '');
            $worst = (string)($pair['worst'] ?? '');
            if (isset($scores[$best])) {
                $scores[$best]['best']++;
            }
            if (isset($scores[$worst])) {
                $scores[$worst]['worst']++;
            }
            $shown = [];
            if (isset($pair['items']) && is_array($pair['items'])) {
                foreach ($pair['items'] as $item) {
                    $shown[] = (string)$item;
                }
            } else {
                if ($best !== '') {
                    $shown[] = $best;
                }
                if ($worst !== '' && $worst !== $best) {
                    $shown[] = $worst;
                }
            }
            foreach (array_unique($shown) as $item) {
                if (isset($scores[$item])) {
                    $scores[$item]['shown']++;
                }
            }
        }
        foreach ($scores as $item => $row) {
            $scores[$item]['score'] = $row['shown'] > 0
                ? (float)(($row['best'] - $row['worst']) / $row['shown'])
                : 0.0;
        }
        return $scores;
    }
}
