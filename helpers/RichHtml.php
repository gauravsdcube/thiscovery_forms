<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace humhub\modules\thiscoveryForms\helpers;

use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\thiscoveryEditor\helpers\EditorHtml;
use Yii;
use yii\helpers\Html;

/**
 * Facade over Thiscovery Editor HTML conversion for stored thank-you and rich-text fields.
 * Existing HumHub markdown still renders; new content is Lexical HTML.
 */
class RichHtml
{
    public static function toEditorHtml(?string $content): string
    {
        if (class_exists(EditorHtml::class)) {
            return EditorHtml::toEditorHtml($content);
        }
        return trim((string) $content);
    }

    public static function toHtml(?string $content): string
    {
        if (class_exists(EditorHtml::class)) {
            return EditorHtml::toHtml($content);
        }

        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        try {
            return RichText::convert($content, RichText::FORMAT_HTML);
        } catch (\Throwable $e) {
            Yii::warning('Thiscovery Forms markdown render failed: ' . $e->getMessage(), 'thiscovery-forms');
            return Html::encode($content);
        }
    }
}
