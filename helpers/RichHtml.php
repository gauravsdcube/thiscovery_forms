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

    /**
     * Wording only, for the translation screen and export.
     * Paragraphs stay separated. A link with an address is written as "label (address)".
     */
    public static function forTranslation(?string $content): string
    {
        $content = trim((string)$content);
        if ($content === '' || !preg_match('/<[^>]+>/', $content)) {
            return $content;
        }

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8"><div id="te-wrap">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $wrap = $dom->getElementById('te-wrap');
        if ($wrap === null) {
            return self::stripTags($content);
        }
        $text = trim(self::plainFromNode($wrap));
        $text = preg_replace('/^[ \t]+/mu', '', $text) ?? $text;
        $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private static function plainFromNode(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return preg_replace('/[ \t]+/u', ' ', $node->textContent) ?? '';
        }
        if (!$node instanceof \DOMElement) {
            return '';
        }
        $name = strtolower($node->nodeName);
        if (in_array($name, ['script', 'style'], true)) {
            return '';
        }
        if ($name === 'br') {
            return "\n";
        }
        if ($name === 'img') {
            return trim($node->getAttribute('alt'));
        }
        if ($name === 'a') {
            return self::linkText($node);
        }
        if (in_array($name, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote'], true)) {
            $text = trim(self::inlineText($node));
            if ($text === '') {
                return '';
            }
            if ($name === 'li') {
                $text = '- ' . $text;
            }
            return $text . "\n\n";
        }

        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::plainFromNode($child);
        }
        return $out;
    }

    private static function inlineText(\DOMElement $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::plainFromNode($child);
        }
        return preg_replace('/[ \t]+/u', ' ', $out) ?? $out;
    }

    private static function linkText(\DOMElement $node): string
    {
        $label = trim(self::inlineText($node));
        $href = trim($node->getAttribute('href'));
        if ($href === '' || $href === '#') {
            return $label;
        }
        if ($label === '' || $label === $href) {
            return $href;
        }
        return $label . ' (' . $href . ')';
    }

    private static function stripTags(string $content): string
    {
        $text = trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    }
}
