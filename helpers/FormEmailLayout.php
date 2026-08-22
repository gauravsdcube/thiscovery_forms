<?php

namespace humhub\modules\thiscoveryForms\helpers;

use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use yii\helpers\Html;

/**
 * Builds the same header / body / footer HTML shell used by other Thiscovery emails.
 */
class FormEmailLayout
{
    public const HEADER_BG = '#f0f4f8';
    public const HEADER_FONT = '#1f2937';
    public const FOOTER_BG = '#f8f9fa';
    public const FOOTER_FONT = '#6b7280';

    public static function defaultColors(): array
    {
        return [
            'header_bg_color' => self::HEADER_BG,
            'header_font_color' => self::HEADER_FONT,
            'footer_bg_color' => self::FOOTER_BG,
            'footer_font_color' => self::FOOTER_FONT,
        ];
    }

    public static function wrap(
        string $body,
        string $header = '',
        string $footer = '',
        ?FormEmailTemplate $template = null
    ): string {
        $colors = self::defaultColors();
        if ($template) {
            $colors['header_bg_color'] = self::hex($template->header_bg_color, self::HEADER_BG);
            $colors['header_font_color'] = self::hex($template->header_font_color, self::HEADER_FONT);
            $colors['footer_bg_color'] = self::hex($template->footer_bg_color, self::FOOTER_BG);
            $colors['footer_font_color'] = self::hex($template->footer_font_color, self::FOOTER_FONT);
        }

        $header = $header !== '' ? self::applyFontColor($header, $colors['header_font_color']) : '';
        $footer = $footer !== '' ? self::applyFontColor($footer, $colors['footer_font_color']) : '';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
        $html .= '<body style="margin:0;padding:0;background:#eef2f6;font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">';
        $html .= '<div style="max-width:640px;margin:24px auto;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">';

        if ($header !== '') {
            $html .= '<div style="background-color:' . Html::encode($colors['header_bg_color']) . ';padding:24px;text-align:center;">';
            $html .= $header;
            $html .= '</div>';
        }

        $html .= '<div style="padding:24px;">' . $body . '</div>';

        if ($footer !== '') {
            $html .= '<div style="background-color:' . Html::encode($colors['footer_bg_color']) . ';padding:20px;text-align:center;font-size:12px;">';
            $html .= $footer;
            $html .= '</div>';
        }

        $html .= '</div></body></html>';

        return $html;
    }

    public static function hex(?string $value, string $fallback): string
    {
        $value = trim((string)$value);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return strtolower($value);
        }
        return $fallback;
    }

    /**
     * Make Thiscovery Editor HTML safer for mail clients (inline button and image styles).
     */
    public static function emailSafeHtml(string $html): string
    {
        if ($html === '' || !str_contains($html, '<')) {
            return $html;
        }

        $html = preg_replace_callback('/<a\b([^>]*)>(.*?)<\/a>/is', static function ($matches) {
            $attrs = $matches[1];
            $inner = $matches[2];
            $isButton = (bool)preg_match('/data-te-node\s*=\s*["\']button["\']/i', $attrs)
                || (bool)preg_match('/class\s*=\s*["\'][^"\']*\bbtn\b/i', $attrs);
            if ($isButton) {
                $styleKey = 'primary';
                if (preg_match('/data-te-style\s*=\s*["\']([^"\']+)["\']/i', $attrs, $styleMatch)) {
                    $styleKey = strtolower($styleMatch[1]);
                } elseif (preg_match('/\bbtn-light\b/i', $attrs)) {
                    $styleKey = 'light';
                } elseif (preg_match('/\bbtn-(?:secondary|default)\b/i', $attrs)) {
                    $styleKey = 'secondary';
                }
                $buttonStyle = self::buttonStyle($styleKey);
                $attrs = self::mergeStyleAttr($attrs, $buttonStyle);
            } elseif (!preg_match('/style\s*=\s*["\'][^"\']*color\s*:/i', $attrs)) {
                $attrs = self::mergeStyleAttr($attrs, 'color:#2563eb;text-decoration:underline;');
            }
            return '<a' . $attrs . '>' . $inner . '</a>';
        }, $html) ?? $html;

        $html = preg_replace_callback('/<img\b([^>]*)>/i', static function ($matches) {
            return '<img' . self::mergeStyleAttr($matches[1], 'max-width:100%;height:auto;border:0;display:block;') . '>';
        }, $html) ?? $html;

        return $html;
    }

    public static function applyFontColor(string $content, string $fontColor): string
    {
        $fontColor = Html::encode($fontColor);
        if (!str_contains($content, '<')) {
            return '<div style="color:' . $fontColor . ';">' . Html::encode($content) . '</div>';
        }

        return preg_replace_callback('/<([^>]+)>/', static function ($matches) use ($fontColor) {
            $tag = $matches[1];
            if (str_starts_with($tag, '/') || str_starts_with($tag, '!')) {
                return $matches[0];
            }
            if (preg_match('/style\s*=\s*["\'][^"\']*color\s*:/i', $tag)) {
                return $matches[0];
            }
            if (preg_match('/style\s*=\s*["\']([^"\']*)["\']/i', $tag, $styleMatches)) {
                $styles = rtrim($styleMatches[1], ';') . '; color:' . $fontColor;
                $tag = preg_replace('/style\s*=\s*["\'][^"\']*["\']/i', 'style="' . $styles . '"', $tag);
            } else {
                $tag .= ' style="color:' . $fontColor . '"';
            }
            return '<' . $tag . '>';
        }, $content) ?? $content;
    }

    protected static function buttonStyle(string $key): string
    {
        $base = 'display:inline-block;padding:12px 22px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;line-height:1.4;';
        return match ($key) {
            'secondary' => $base . 'background:#e5e7eb;color:#1f2937;',
            'light' => $base . 'background:#f8fafc;color:#1f2937;border:1px solid #e5e7eb;',
            default => $base . 'background:#2563eb;color:#ffffff;',
        };
    }

    protected static function mergeStyleAttr(string $attrs, string $extra): string
    {
        $extra = rtrim($extra, ';') . ';';
        if (preg_match('/style\s*=\s*(["\'])([^"\']*)\1/i', $attrs, $m)) {
            $merged = rtrim($m[2], ';') . ';' . $extra;
            return preg_replace('/style\s*=\s*(["\'])[^"\']*\1/i', 'style="' . $merged . '"', $attrs, 1) ?? $attrs;
        }
        return rtrim($attrs) . ' style="' . $extra . '"';
    }
}
