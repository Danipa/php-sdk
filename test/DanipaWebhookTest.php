<?php

declare(strict_types=1);

/**
 * Standalone test for DanipaWebhook — no PHPUnit dependency.
 * Run: php test/DanipaWebhookTest.php
 */

require_once __DIR__ . '/../src/DanipaWebhook.php';

use Danipa\Sdk\DanipaWebhook;

$passed = 0;
$failed = 0;

$SECRET  = 'whsec_test_secret_for_unit_tests';
$PAYLOAD = '{"event":"payment.completed","amount":100}';

function test(string $name, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  PASS: $name\n";
    } else {
        $failed++;
        echo "  FAIL: $name\n";
    }
}

// Valid signature accepted
$ts  = time();
$sig = DanipaWebhook::computeSignature($PAYLOAD, $SECRET, $ts);
test('valid signature accepted',
    DanipaWebhook::verify($PAYLOAD, $sig, (string)$ts, $SECRET));

// Invalid signature rejected
test('invalid signature rejected',
    !DanipaWebhook::verify($PAYLOAD, 'sha256=0000', (string)$ts, $SECRET));

// Expired timestamp rejected
$oldTs = time() - 600;
$oldSig = DanipaWebhook::computeSignature($PAYLOAD, $SECRET, $oldTs);
test('expired timestamp rejected',
    !DanipaWebhook::verify($PAYLOAD, $oldSig, (string)$oldTs, $SECRET));

// Future timestamp rejected
$futureTs = time() + 600;
$futureSig = DanipaWebhook::computeSignature($PAYLOAD, $SECRET, $futureTs);
test('future timestamp rejected',
    !DanipaWebhook::verify($PAYLOAD, $futureSig, (string)$futureTs, $SECRET));

// Empty inputs rejected
test('empty payload rejected',
    !DanipaWebhook::verify('', 'sig', '123', $SECRET));
test('empty signature rejected',
    !DanipaWebhook::verify($PAYLOAD, '', '123', $SECRET));
test('empty timestamp rejected',
    !DanipaWebhook::verify($PAYLOAD, 'sig', '', $SECRET));
test('empty secret rejected',
    !DanipaWebhook::verify($PAYLOAD, 'sig', '123', ''));

// Signature format
$sig2 = DanipaWebhook::computeSignature($PAYLOAD, $SECRET, 1700000000);
test('signature starts with sha256=',
    str_starts_with($sig2, 'sha256='));
test('signature hex is 64 chars',
    strlen(substr($sig2, 7)) === 64);

// Different timestamps produce different signatures
$sigA = DanipaWebhook::computeSignature($PAYLOAD, $SECRET, 1700000000);
$sigB = DanipaWebhook::computeSignature($PAYLOAD, $SECRET, 1700000001);
test('different timestamps produce different signatures',
    $sigA !== $sigB);

// Cross-language compatibility — known vector
$knownSig = DanipaWebhook::computeSignature('{"test":true}', 'whsec_fixed_test_secret', 1700000000);
test('known vector format', str_starts_with($knownSig, 'sha256='));
echo "    Known vector: $knownSig\n";

echo "\n=== Results: $passed passed, $failed failed ===\n";
if ($failed > 0) exit(1);
