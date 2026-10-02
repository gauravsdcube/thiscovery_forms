<?php
/** DAT-16: a draft reopens on its saved page by key, not by a position that has moved. */
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../services/FormPager.php';

use humhub\modules\thiscoveryForms\services\FormPager;

$failures = [];
$pages = [
    ['index' => 0, 'pageKey' => 'start'],
    ['index' => 1, 'pageKey' => 'consent'],
    ['index' => 2, 'pageKey' => 'about'],
    ['index' => 3, 'pageKey' => 'loop'],
    ['index' => 4, 'pageKey' => 'loop'],
];
// Saved on "about" when it was page 1; a consent page has since been added in front of it.
standalone_assert(FormPager::resumeIndex($pages, 1, 'about') === 2, 'the key did not win over a moved position', $failures);
// Loop repeats share a key; the saved repeat is kept.
standalone_assert(FormPager::resumeIndex($pages, 4, 'loop') === 4, 'the saved loop repeat was lost', $failures);
// A key that no longer exists falls back to the position, and a position past the end to page 0.
standalone_assert(FormPager::resumeIndex($pages, 2, 'gone') === 2, 'a missing key did not fall back to the position', $failures);
standalone_assert(FormPager::resumeIndex($pages, 9, '') === 0, 'a position past the end was used', $failures);
standalone_assert(FormPager::resumeIndex($pages, 3, '') === 3, 'a plain position was not used', $failures);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
exit(0);
