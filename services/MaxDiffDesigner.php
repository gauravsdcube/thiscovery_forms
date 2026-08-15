<?php

namespace humhub\modules\thiscoveryForms\services;

/**
 * v1 MaxDiff: cyclic / BIBD-like sets and count scores (best minus worst).
 */
class MaxDiffDesigner
{
    /**
     * @param string[] $items
     * @return string[][]
     */
    public function generateSets(array $items, int $setSize, int $setCount): array
    {
        $items = array_values(array_filter(array_map('strval', $items), 'strlen'));
        $n = count($items);
        if ($n < 2) {
            return [];
        }
        $setSize = max(2, min($setSize, $n));
        $setCount = max(1, $setCount);
        $step = max(1, (int)floor($n / $setSize));
        $sets = [];
        $seen = [];

        for ($s = 0; $s < $setCount * 4 && count($sets) < $setCount; $s++) {
            $set = [];
            for ($j = 0; $j < $setSize; $j++) {
                $idx = ($s + ($j * $step)) % $n;
                $set[] = $items[$idx];
            }
            $set = array_values(array_unique($set));
            $cursor = $s % $n;
            while (count($set) < $setSize) {
                $candidate = $items[$cursor % $n];
                if (!in_array($candidate, $set, true)) {
                    $set[] = $candidate;
                }
                $cursor++;
                if ($cursor - $s > $n) {
                    break;
                }
            }
            $key = implode("\0", $set);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $sets[] = $set;
        }

        while (count($sets) < $setCount) {
            $sets[] = array_slice($items, 0, $setSize);
        }

        return array_slice($sets, 0, $setCount);
    }

    /**
     * @param string[] $items
     * @param array $responses list of set answers: [['best' => x, 'worst' => y], ...]
     * @return array<string, array{best:int,worst:int,score:int}>
     */
    public function scores(array $items, array $responses): array
    {
        $scores = [];
        foreach ($items as $item) {
            $item = (string)$item;
            $scores[$item] = ['best' => 0, 'worst' => 0, 'score' => 0];
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
        }
        foreach ($scores as $item => $row) {
            $scores[$item]['score'] = $row['best'] - $row['worst'];
        }
        return $scores;
    }
}
