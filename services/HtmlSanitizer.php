<?php

namespace humhub\modules\thiscoveryForms\services;

use yii\helpers\HtmlPurifier;

class HtmlSanitizer
{
    /**
     * Sanitize creator HTML for safe fill-form rendering (no scripts/handlers).
     */
    public function sanitize(string $html): string
    {
        return HtmlPurifier::process($html, function ($config) {
            /** @var \HTMLPurifier_Config $config */
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.Allowed', implode(',', [
                'p', 'br', 'span[class|style]', 'div[class|style]', 'section[class]', 'article[class]',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
                'ul', 'ol', 'li',
                'a[href|title|target|rel|class]',
                'img[src|alt|title|width|height|class]',
                'table', 'thead', 'tbody', 'tr', 'th', 'td',
                'blockquote', 'pre', 'code', 'hr',
                'iframe[src|width|height|frameborder|allowfullscreen]',
                'label[for|class]',
                'input[type|name|value|placeholder|required|checked|min|max|step|class|id]',
                'textarea[name|rows|cols|placeholder|required|class|id]',
                'select[name|required|multiple|class|id]',
                'option[value|selected]',
                'button[type|class]',
            ]));
            $config->set('CSS.AllowedProperties', [
                'color', 'background-color', 'text-align', 'font-size', 'font-weight',
                'margin', 'margin-top', 'margin-bottom', 'padding', 'padding-top', 'padding-bottom',
                'width', 'max-width', 'height', 'border', 'border-radius',
            ]);

            $def = $config->getHTMLDefinition(true);
            if ($def) {
                $def->addAttribute('input', 'data-cf-html-var', 'Text');
                $def->addAttribute('textarea', 'data-cf-html-var', 'Text');
                $def->addAttribute('select', 'data-cf-html-var', 'Text');
            }
        });
    }
}
