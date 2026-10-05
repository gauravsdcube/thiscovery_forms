<?php
/**
 * Translation export should show rich text as wording, without HTML tags.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\helpers\RichHtml;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL $message\n");
    }
};

$plain = '<p class="te-p"><span style="white-space: pre-wrap;">All questions are optional.</span></p>';
$clean = RichHtml::forTranslation($plain);
$check($clean === 'All questions are optional.', 'A paragraph is the wording only. Got: ' . $clean);
$check(!str_contains($clean, '<'), 'No tags remain.');

$linked = '<h4 class="te-h4"><span style="white-space: pre-wrap;">Support</span></h4>'
    . '<ul class="te-ul"><li value="1" class="te-li"><span style="white-space: pre-wrap;">Bliss: </span>'
    . '<a href="https://www.bliss.org.uk/parents" target="_blank" rel="noopener" class="te-link">'
    . '<span style="white-space: pre-wrap;">resources</span></a></li></ul>';
$cleanLink = RichHtml::forTranslation($linked);
$check($cleanLink === "Support\n\n- Bliss: resources (https://www.bliss.org.uk/parents)", 'A heading, list, and link stay readable. Got: ' . $cleanLink);

$hindi = '<p>  हम नवजात यूनिट छोड़ने के बाद परिवारों के अनुभवों को समझना चाहते हैं।  </p> <p>  यह सर्वे कैंब्रिज यूनिवर्सिटी के शोधकर्ताओं द्वारा चलाया जा रहा है। कृपया  <a href=""></a></p>';
$cleanHindi = RichHtml::forTranslation($hindi);
$check(
    $cleanHindi === "हम नवजात यूनिट छोड़ने के बाद परिवारों के अनुभवों को समझना चाहते हैं।\n\nयह सर्वे कैंब्रिज यूनिवर्सिटी के शोधकर्ताओं द्वारा चलाया जा रहा है। कृपया",
    'Translated paragraphs drop empty links. Got: ' . $cleanHindi
);

$check(RichHtml::forTranslation('<p class="te-p"><br></p>') === '', 'An empty editor paragraph is left blank.');
$check(RichHtml::forTranslation('Plain sentence.') === 'Plain sentence.', 'Plain text is unchanged.');
$check(RichHtml::forTranslation($clean) === $clean, 'Cleaning a second time does not change the text.');

if ($failures) {
    fwrite(STDERR, count($failures) . " failed\n");
    exit(1);
}
echo "PASS\n";
