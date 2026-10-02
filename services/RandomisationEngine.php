<?php

namespace humhub\modules\thiscoveryForms\services;

/**
 * Pure draws. The same seed always returns the same order. No database and no user id.
 */
class RandomisationEngine
{
    /**
     * Fisher–Yates driven by a SHA-256 counter stream, with rejection sampling so
     * every order is equally likely (V3-5). The same seed always returns the same order.
     *
     * @param string[] $items
     * @return string[]
     */
    public function shuffle(array $items, int $seed): array
    {
        $items = array_values($items);
        $draw = $this->stream($seed);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = $draw($i + 1);
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }
        return $items;
    }

    /**
     * A deterministic source of unbiased integers in [0, bound).
     *
     * @return \Closure(int):int
     */
    public function stream(int $seed): \Closure
    {
        $counter = 0;
        $buffer = '';
        return static function (int $bound) use ($seed, &$counter, &$buffer): int {
            if ($bound < 2) {
                return 0;
            }
            // Largest multiple of $bound below 2^32; values at or above it are rejected.
            $limit = intdiv(0x100000000, $bound) * $bound;
            while (true) {
                if (strlen($buffer) < 4) {
                    $buffer .= hash('sha256', $seed . ':' . $counter, true);
                    $counter++;
                }
                $value = unpack('N', substr($buffer, 0, 4))[1];
                $buffer = substr($buffer, 4);
                if ($value < $limit) {
                    return $value % $bound;
                }
            }
        };
    }

    /**
     * One cyclic shift. Offset 1 moves the first item to the end.
     *
     * @param string[] $items
     * @return string[]
     */
    public function rotate(array $items, int $offset): array
    {
        $items = array_values($items);
        $n = count($items);
        if ($n < 2) {
            return $items;
        }
        $offset = $offset % $n;
        if ($offset < 0) {
            $offset += $n;
        }
        if ($offset === 0) {
            return $items;
        }
        return array_merge(array_slice($items, $offset), array_slice($items, 0, $offset));
    }

    /**
     * Pin, then shuffle or rotate the middle, then keep the first $show of that middle.
     *
     * @param string[] $items
     * @param array{method?:string,pinFirst?:string[],pinLast?:string[],show?:int|null} $config
     * @return array{order:string[],shown:string[]}
     */
    public function present(array $items, array $config, int $seed, int $rotateOffset = 0): array
    {
        $items = array_values(array_map('strval', $items));
        $pinFirst = $this->known($items, $config['pinFirst'] ?? []);
        $pinLast = $this->known($items, $config['pinLast'] ?? []);
        $pinned = array_fill_keys(array_merge($pinFirst, $pinLast), true);
        $middle = [];
        foreach ($items as $item) {
            if (!isset($pinned[$item])) {
                $middle[] = $item;
            }
        }
        $method = (($config['method'] ?? '') === 'rotate') ? 'rotate' : 'shuffle';
        if ($method === 'rotate') {
            $middle = $this->rotate($middle, $rotateOffset);
        } else {
            $middle = $this->shuffle($middle, $seed);
        }
        $show = $config['show'] ?? null;
        if ($show !== null && (int)$show >= 0 && (int)$show < count($middle)) {
            $middle = array_slice($middle, 0, (int)$show);
        }
        $order = array_merge($pinFirst, $middle, $pinLast);
        return ['order' => $order, 'shown' => $order];
    }

    /**
     * @param array<int, array{code:string,weight:int}> $arms
     * @return string[] one block, each code repeated by its weight
     */
    public function block(array $arms, int $blockSize, int $seed): array
    {
        $unit = $this->weightedUnit($arms);
        if (!$unit) {
            return [];
        }
        // Whole multiples of the weighted unit only, so every block keeps the ratio (V3-48).
        $blockSize = max(count($unit), $blockSize);
        $blockSize = (int)(ceil($blockSize / count($unit)) * count($unit));
        $slots = [];
        while (count($slots) < $blockSize) {
            foreach ($unit as $code) {
                $slots[] = $code;
                if (count($slots) >= $blockSize) {
                    break;
                }
            }
        }
        return $this->shuffle($slots, $seed);
    }

    /**
     * @param array<int, array{code:string,weight:int}> $arms
     */
    public function weightedPick(array $arms, int $seed): string
    {
        $unit = $this->weightedUnit($arms);
        if (!$unit) {
            return '';
        }
        return $unit[($this->stream($seed))(count($unit))];
    }

    /**
     * @param array<string, int> $counts code => assigned
     * @param string[] $eligible
     */
    /**
     * Least-filled with a random element: 80% of the time the least-filled arm (ties at
     * random), otherwise a weighted draw over all arms. Balance is kept, but the next arm
     * cannot be worked out from the counts (allocation concealment, V3-48).
     *
     * @param array<string,int> $counts
     * @param array<int, array{code:string,weight:int}> $arms
     * @param string[] $eligible
     */
    public function minimise(array $counts, array $arms, array $eligible, int $seed): string
    {
        $next = $this->stream($seed);
        if ($next(100) < 80) {
            return $this->leastFilled($counts, $eligible, $seed ^ 0x5A5A5A5A);
        }
        return $this->weightedPick($arms, $seed ^ 0x3C3C3C3C);
    }

    public function leastFilled(array $counts, array $eligible, int $seed): string
    {
        $eligible = array_values(array_unique(array_map('strval', $eligible)));
        if (!$eligible) {
            return '';
        }
        $best = null;
        $bestCount = null;
        foreach ($eligible as $code) {
            $n = (int)($counts[$code] ?? 0);
            if ($bestCount === null || $n < $bestCount) {
                $best = $code;
                $bestCount = $n;
            }
        }
        $tied = [];
        foreach ($eligible as $code) {
            if ((int)($counts[$code] ?? 0) === $bestCount) {
                $tied[] = $code;
            }
        }
        sort($tied);
        return $tied[($this->stream($seed))(count($tied))];
    }

    /**
     * Independent 60-bit seed per scope. HMAC-SHA256 keyed by the response seed, so two
     * scopes of one response are unrelated (crc32 was linear and correlated them, V3-5).
     */
    public function seedInt(string $seedHex, string $scope): int
    {
        return (int)hexdec(substr(hash_hmac('sha256', $scope, $seedHex), 0, 15));
    }

    /**
     * @param string[] $items
     * @param string[] $wanted
     * @return string[]
     */
    private function known(array $items, array $wanted): array
    {
        $have = array_fill_keys($items, true);
        $out = [];
        foreach ($wanted as $code) {
            $code = (string)$code;
            if ($code !== '' && isset($have[$code]) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /**
     * @param array<int, array{code:string,weight:int}> $arms
     * @return string[]
     */
    private function weightedUnit(array $arms): array
    {
        $unit = [];
        foreach ($arms as $arm) {
            $code = trim((string)($arm['code'] ?? ''));
            $weight = max(0, (int)($arm['weight'] ?? 0));
            if ($code === '' || $weight < 1) {
                continue;
            }
            for ($i = 0; $i < $weight; $i++) {
                $unit[] = $code;
            }
        }
        return $unit;
    }
}
