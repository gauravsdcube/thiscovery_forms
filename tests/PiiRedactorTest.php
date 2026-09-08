<?php

use humhub\modules\thiscoveryForms\services\PiiRedactor;

require __DIR__ . '/../services/PiiRedactor.php';

$redactor = new PiiRedactor();
$failed = 0;

$cases = [
    ['Contact me at jane.doe@example.org please', 'Contact me at [redacted] please', 'email in sentence'],
    ['+447700900123', '[redacted]', 'E.164 mobile'],
    ['Call 07700 900123 today', 'Call [redacted] today', 'UK mobile with spaces'],
    ['Server 192.168.0.1 logged in', 'Server [redacted] logged in', 'IPv4'],
    ['Host 2001:db8::1 replied', 'Host [redacted] replied', 'IPv6'],
    ['Postcode SW1A 1AA stays', 'Postcode SW1A 1AA stays', 'UK postcode untouched'],
    ['NHS 943 476 5919 stays', 'NHS 943 476 5919 stays', 'NHS number untouched'],
    ['Plain text', 'Plain text', 'no PII'],
];

foreach ($cases as [$input, $expected, $name]) {
    $got = $redactor->redact($input);
    if ($got !== $expected) {
        fwrite(STDERR, "FAIL {$name}: expected " . json_encode($expected) . ' got ' . json_encode($got) . PHP_EOL);
        $failed++;
    } else {
        fwrite(STDOUT, "ok {$name}" . PHP_EOL);
    }
}

if ($failed) {
    fwrite(STDERR, "{$failed} fixture(s) failed" . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "PiiRedactor fixtures passed" . PHP_EOL);
exit(0);
