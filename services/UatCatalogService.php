<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\Module;
use Yii;

/**
 * Reads the UAT scenario catalog from docs/user/uat-scenarios.csv.
 */
class UatCatalogService
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function all(): array
    {
        $path = self::csvPath();
        if (!is_readable($path)) {
            return [];
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return [];
        }
        $header = array_map(static fn ($h) => trim((string)$h), $header);

        $rows = [];
        while (($data = fgetcsv($fh)) !== false) {
            if (count($data) === 1 && trim((string)$data[0]) === '') {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($data[$i]) ? trim((string)$data[$i]) : '';
            }
            if (($row['Test ID'] ?? '') === '') {
                continue;
            }
            $rows[] = self::normalize($row);
        }
        fclose($fh);
        return $rows;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function byId(): array
    {
        $map = [];
        foreach (self::all() as $row) {
            $map[$row['id']] = $row;
        }
        return $map;
    }

    public static function find(string $testId): ?array
    {
        $testId = trim($testId);
        return self::byId()[$testId] ?? null;
    }

    /**
     * Options for a select: id => "UAT-XXX — Scenario".
     *
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        $opts = [];
        foreach (self::all() as $row) {
            $opts[$row['id']] = $row['id'] . ' — ' . $row['scenario'];
        }
        return $opts;
    }

    /**
     * Grouped options: feature => [id => label].
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedSelectOptions(): array
    {
        $groups = [];
        foreach (self::all() as $row) {
            $feat = $row['feature'] ?: 'Other';
            $groups[$feat][$row['id']] = $row['id'] . ' — ' . $row['scenario'];
        }
        return $groups;
    }

    public static function csvPath(): string
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-forms');
        return $module->getBasePath() . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'uat-scenarios.csv';
    }

    public static function count(): int
    {
        return count(self::all());
    }

    /**
     * Numbered steps from the catalog "1. … | 2. …" string.
     *
     * @return string[]
     */
    public static function stepList(string $steps): array
    {
        $parts = preg_split('/\s+\|\s+/u', trim($steps)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            $part = preg_replace('/^\d+\.\s*/u', '', $part) ?? $part;
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return $out;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, string>
     */
    protected static function normalize(array $row): array
    {
        return [
            'id' => $row['Test ID'] ?? '',
            'feature' => $row['Feature'] ?? '',
            'scenario' => $row['Scenario'] ?? '',
            'explanation' => $row['Explanation'] ?? '',
            'preconditions' => $row['Preconditions'] ?? '',
            'steps' => $row['Steps'] ?? '',
            'expected' => $row['Expected behaviour'] ?? '',
            'priority' => $row['Priority'] ?? '',
            'roles' => $row['Roles'] ?? '',
        ];
    }
}
