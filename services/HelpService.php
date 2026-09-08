<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\Module;
use Yii;
use yii\helpers\Markdown;

/**
 * In-product Help from docs/user markdown.
 */
class HelpService
{
    /**
     * @return array<int, array{id:string,title:string,intro:string,pages:string[]}>
     */
    public static function sections(): array
    {
        return [
            [
                'id' => 'admin',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Administration'),
                'intro' => Yii::t('ThiscoveryFormsModule.base', 'Enable the module, choose form types, and decide who can create, fill, and see answers.'),
                'pages' => ['admins'],
            ],
            [
                'id' => 'creators',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Form creators'),
                'intro' => Yii::t('ThiscoveryFormsModule.base', 'Build forms, invite people, and work with results.'),
                'pages' => [
                    'creators-getting-started',
                    'creators-builder',
                    'creators-csv-import',
                    'creators-settings',
                    'creators-versioning',
                    'creators-response-integrity',
                    'creators-results',
                    'creators-panels',
                    'creators-form-types',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{file:string,title:string,summary:string,icon:string}>
     */
    public static function pages(): array
    {
        return [
            'admins' => [
                'file' => 'admins.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms for administrators'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Module enablement, configuration, permissions, and network versus space forms.'),
                'icon' => 'cog',
            ],
            'creators-getting-started' => [
                'file' => 'creators-getting-started.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Getting started'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Where forms live, how to create one, and the studio tabs.'),
                'icon' => 'play-circle',
            ],
            'creators-builder' => [
                'file' => 'creators-builder.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Builder and questions'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Question types, pages, logic, piping, variables, and field actions.'),
                'icon' => 'th-list',
            ],
            'creators-csv-import' => [
                'file' => 'creators-csv-import.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Import questions from CSV'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Field types, columns, page breaks, and whether to replace or append.'),
                'icon' => 'file-excel-o',
            ],
            'creators-settings' => [
                'file' => 'creators-settings.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Form settings'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Status, who can take part, export CSV columns, emails, languages, and custom functions.'),
                'icon' => 'wrench',
            ],
            'creators-versioning' => [
                'file' => 'creators-versioning.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Versions and publishing'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Revisions, editions, publish before Open, restore, and open-period history.'),
                'icon' => 'history',
            ],
            'creators-response-integrity' => [
                'file' => 'creators-response-integrity.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Response integrity'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Bot protection, quality scores, review, exclusion, and access modes.'),
                'icon' => 'shield',
            ],
            'creators-results' => [
                'file' => 'creators-results.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Sharing and results'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Share links, preview, dashboards, CSV columns, and PII scrubbing.'),
                'icon' => 'bar-chart',
            ],
            'creators-panels' => [
                'file' => 'creators-panels.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Panels, waves, and email'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'Panels, wave calendars, and email templates.'),
                'icon' => 'users',
            ],
            'creators-form-types' => [
                'file' => 'creators-form-types.md',
                'title' => Yii::t('ThiscoveryFormsModule.base', 'Form types'),
                'summary' => Yii::t('ThiscoveryFormsModule.base', 'When to use a survey, poll, longitudinal, consensus, or project form.'),
                'icon' => 'files-o',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function downloadFiles(): array
    {
        return [];
    }

    /**
     * @return array<int, array{file:string,label:string}>
     */
    public static function downloadsFor(string $slug): array
    {
        return [];
    }

    public static function resolveDownload(string $file): ?string
    {
        return null;
    }

    public static function downloadMime(string $file): string
    {
        return 'application/octet-stream';
    }

    public static function find(string $slug): ?array
    {
        $pages = self::pages();
        return $pages[$slug] ?? null;
    }

    /**
     * @return array{slug:string,title:string,html:string}|null
     */
    public static function render(string $slug, $container = null): ?array
    {
        $meta = self::find($slug);
        if (!$meta) {
            return null;
        }

        $path = self::docsPath() . DIRECTORY_SEPARATOR . $meta['file'];
        if (!is_readable($path)) {
            return null;
        }

        $markdown = (string)file_get_contents($path);
        $markdown = preg_replace('/^#\s+.*\R+/', '', $markdown, 1) ?? $markdown;
        $markdown = preg_replace_callback(
            '/\]\(([\w-]+)\.md(#[^)]+)?\)/',
            static function (array $m) use ($container): string {
                $url = Url::toHelp($container, $m[1]);
                return '](' . $url . ($m[2] ?? '') . ')';
            },
            $markdown
        ) ?? $markdown;

        return [
            'slug' => $slug,
            'title' => $meta['title'],
            'html' => Markdown::process($markdown, 'gfm'),
        ];
    }

    public static function docsPath(): string
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-forms');
        return $module->getBasePath() . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'user';
    }
}
