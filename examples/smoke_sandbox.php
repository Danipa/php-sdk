<?php

/**
 * Live smoke test against the Danipa SANDBOX. Not run in CI.
 *
 *   cd sdks/php && composer install
 *   DANIPA_API_KEY=dk_test_... php examples/smoke_sandbox.php
 *
 * Walks the whole API-key surface once: me → balances → payment link
 * create/list/update/delete → invoice create/get/send/void. It prints only
 * step names and OK / FAIL / SKIP (a failure adds the HTTP status and error
 * code), never the key or response bodies. It creates one payment link (then
 * deletes it) and one invoice (then voids it); the invoice has no customer
 * email or phone, so sending it notifies nobody. Exits 1 if any step fails.
 *
 * Optional: DANIPA_BASE_URL to point at another sandbox-like host.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Danipa\Sdk\Danipa;
use Danipa\Sdk\Errors\DanipaApiError;
use Danipa\Sdk\Model\CreateInvoiceRequest;
use Danipa\Sdk\Model\CreatePaymentLinkRequest;
use Danipa\Sdk\Model\InvoiceLineItemRequest;
use Danipa\Sdk\Model\UpdatePaymentLinkRequest;

$key = getenv('DANIPA_API_KEY');
if (!is_string($key) || $key === '') {
    fwrite(STDERR, "DANIPA_API_KEY is not set\n");
    exit(2);
}
if (!str_starts_with($key, 'dk_test_')) {
    fwrite(STDERR, "refusing to run: DANIPA_API_KEY must be a sandbox (dk_test_) key\n");
    exit(2);
}

$builder = Danipa::builder()->apiKey($key);
$baseUrl = getenv('DANIPA_BASE_URL');
if (is_string($baseUrl) && $baseUrl !== '') {
    $builder->baseUrl($baseUrl);
}
$danipa = $builder->build();
unset($key);

$failed = false;

/**
 * Run one step. Returns the step's result, or null when it failed or was skipped.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|null
 */
$step = static function (string $name, callable $fn, bool $skip = false) use (&$failed): mixed {
    if ($skip) {
        printf("%-24s SKIP\n", $name);
        return null;
    }
    try {
        $result = $fn();
        printf("%-24s OK\n", $name);
        return $result;
    } catch (DanipaApiError $e) {
        printf("%-24s FAIL (HTTP %d %s)\n", $name, $e->status, $e->errorCode);
    } catch (\Throwable $e) {
        printf("%-24s FAIL (%s)\n", $name, (new \ReflectionClass($e))->getShortName());
    }
    $failed = true;
    return null;
};

$step('merchants.me', static fn () => $danipa->merchants()->me());
$step('balances.list', static fn () => $danipa->balances()->list());

$link = $step('paymentLinks.create', static fn () => $danipa->paymentLinks()->create(new CreatePaymentLinkRequest(
    title: 'SDK smoke test (php)',
    amount: '1.00',
)));
$step('paymentLinks.list', static fn () => $danipa->paymentLinks()->list(size: 5));
$step(
    'paymentLinks.update',
    static fn () => $danipa->paymentLinks()->update($link->id, new UpdatePaymentLinkRequest(amount: '2.00')),
    $link === null,
);
$step('paymentLinks.delete', static fn () => $danipa->paymentLinks()->delete($link->id), $link === null);

$invoice = $step('invoices.create', static fn () => $danipa->invoices()->create(new CreateInvoiceRequest(
    customerName: 'SDK smoke test (php)',
    lineItems: [new InvoiceLineItemRequest('Smoke test line', 1, '1.00')],
)));
$step('invoices.get', static fn () => $danipa->invoices()->get($invoice->id), $invoice === null);
$step('invoices.send', static fn () => $danipa->invoices()->send($invoice->id), $invoice === null);
$step('invoices.void', static fn () => $danipa->invoices()->void($invoice->id), $invoice === null);

echo $failed ? "SMOKE FAIL\n" : "SMOKE OK\n";
exit($failed ? 1 : 0);
