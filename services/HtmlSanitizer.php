<?php

namespace humhub\modules\thiscoveryForms\services;

use HTMLPurifier_AttrDef_Text;
use yii\helpers\HtmlPurifier;

class HtmlSanitizer
{
    /**
     * Sanitize creator HTML for safe fill-form rendering (no scripts/handlers).
     *
     * HTML Purifier does not support HTML5 elements such as section/article; listing
     * them in HTML.Allowed raises a warning that Yii treats as a fatal error.
     * Use only HTML.Allowed (not HTML.AllowedAttributes): setting both makes Allowed
     * a no-op, which leaves HTML.Forms' required form[action] and crashes Yii.
     */
    public function sanitize(string $html): string
    {
        return HtmlPurifier::process($html, function ($config) {
            /** @var \HTMLPurifier_Config $config */
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('HTML.SafeIframe', true);
            $config->set('HTML.Forms', true);
            $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('CSS.AllowedProperties', [
                'color', 'background-color', 'text-align', 'font-size', 'font-weight',
                'margin', 'margin-top', 'margin-bottom', 'padding', 'padding-top', 'padding-bottom',
                'width', 'max-width', 'height', 'border', 'border-radius',
            ]);
            $config->set('HTML.Allowed', implode(',', [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'b', 'i', 'strong', 'em', 'u', 's', 'sub', 'sup',
                'p[class|style]', 'div[class|style]', 'span[class|style]', 'pre', 'code', 'blockquote', 'hr',
                'ul', 'ol', 'li',
                'a[href|title|target|rel|class]',
                'img[src|alt|title|width|height|class]',
                'table', 'thead', 'tbody', 'tr', 'th[align]', 'td[align]',
                'iframe[src|width|height|frameborder]',
                'label[class]',
                'input[type|name|value|checked|class]',
                'textarea[name|rows|cols|class]',
                'select[name|multiple|class]',
                'option[value|selected]',
                'button[type|class]',
            ]));

            $def = $config->getHTMLDefinition();
            if ($def) {
                $text = new HTMLPurifier_AttrDef_Text();
                if (isset($def->info['a'])) {
                    $def->info['a']->attr['target'] = $text;
                }
                if (isset($def->info['label'])) {
                    $def->info['label']->attr['for'] = $text;
                }
                foreach (['input', 'textarea', 'select'] as $el) {
                    if (isset($def->info[$el])) {
                        $def->info[$el]->attr['data-cf-html-var'] = $text;
                    }
                }
            }
        });
    }
}
