<?php
// Trial 015 hidden suite: what a maintainer would check before merging.
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message): bool {
    throw new ErrorException($message, 0, $severity);
});
require $argv[1] . '/bootstrap.php';

use App\Cart\Address;
use App\Cart\Cart;
use App\Cart\CartItem;
use App\Hooks\Filters;

$results = [];
function check(string $group, string $name, callable $test): void
{
    global $results;
    try {
        $test();
        $results[$group][$name] = true;
    } catch (Throwable $error) {
        $results[$group][$name] = false;
        if (getenv('HIDDEN_DEBUG')) {
            fwrite(STDERR, "$group/$name: " . get_class($error) . ' ' . $error->getMessage() . "\n");
        }
    }
}
/** @param mixed $expected @param mixed $actual */
function same($expected, $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}
final class CountingProvider implements App\Shipping\RateProvider
{
    public int $calls = 0;
    public function quote(string $method, Address $destination, array $package): int
    {
        $this->calls++;
        $weight = 0;
        foreach ($package as $p) {
            $weight += $p['weight'] * $p['quantity'];
        }
        return ($method === 'express' ? 990 : 490) + intdiv($weight, 1000) * 100 + ($destination->country === 'DE' ? 0 : 500);
    }
}
function cart(?Filters $filters = null, string $method = 'flat_rate'): Cart
{
    $cart = new Cart($filters ?? new Filters(), new Address('AT', '1010'));
    $cart->shipping = new Address('FR', '75001');
    $cart->shippingMethod = $method;
    return $cart;
}
function resolver(): App\Tax\TaxLocationResolver
{
    return new App\Tax\TaxLocationResolver(new Address('DE', '80331'));
}
function decline(string $class, array $response): ?App\Exception\PaymentFailed
{
    $gateway = new $class(function (array $request) use ($response): array { return $response; });
    try {
        $gateway->charge(1000, 'tok');
    } catch (App\Exception\PaymentFailed $e) {
        return $e;
    }
    return null;
}
function validAt(string $nowUtc, string $tz, string $expires): bool
{
    return (new App\Coupons\CouponValidator(new App\Support\FrozenClock($nowUtc), $tz))->isValid(new App\Coupons\Coupon('X', $expires));
}
function csv(array $orders): string
{
    return (new App\Export\OrderCsv())->render($orders);
}
/** Parse CSV output per RFC 4180 (any valid quoting style is accepted). */
function rows(string $csv): array
{
    if (substr($csv, -2) !== "\r\n") {
        throw new RuntimeException('records must end with CRLF');
    }
    $h = fopen('php://memory', 'r+');
    fwrite($h, $csv);
    rewind($h);
    $out = [];
    while (($row = fgetcsv($h, 0, ',', '"', "\0")) !== false) {
        $out[] = $row;
    }
    return $out;
}
function order(string $customer, string $note = '', int $total = 1250, string $number = 'O-1'): array
{
    return ['number' => $number, 'customer' => $customer, 'note' => $note, 'total_cents' => $total];
}
$declines = [
    'StripeGateway' => [['status' => 'failed', 'decline_code' => 'insufficient_funds', 'message' => 'Your card has insufficient funds.'], 'insufficient_funds', 'Your card has insufficient funds.'],
    'PayPalGateway' => [['status' => 'DECLINED', 'issue' => 'INSTRUMENT_DECLINED', 'description' => 'The instrument was declined.'], 'INSTRUMENT_DECLINED', 'The instrument was declined.'],
    'KlarnaGateway' => [['fraud_status' => 'REJECTED', 'error_code' => 'NOT_APPROVED', 'error_message' => 'Not approved.'], 'NOT_APPROVED', 'Not approved.'],
    'SepaGateway' => [['state' => 'rejected', 'reason_code' => 'AM04', 'reason_text' => 'Insufficient funds.'], 'AM04', 'Insufficient funds.'],
    'GiftCardGateway' => [['result' => 'error', 'status_code' => 'expired', 'status_text' => 'Gift card expired.'], 'expired', 'Gift card expired.'],
];

// ---------- functional: the examples in the issues ----------
check('functional', '201-local-pickup-uses-store', function (): void {
    $cart = cart(null, 'local_pickup');
    $cart->add(new CartItem('mug', 1, 1290, 400));
    same('DE', resolver()->resolve($cart)->country);
});
check('functional', '202-cost-follows-cart', function (): void {
    $provider = new CountingProvider();
    $calc = new App\Shipping\ShippingCalculator($provider);
    $cart = cart();
    $cart->add(new CartItem('mug', 1, 1290, 400));
    same(990, $calc->cost($cart));
    $cart->add(new CartItem('parcel', 1, 5000, 20000));
    same(2990, $calc->cost($cart));
});
check('functional', '203-named-gateways', function () use ($declines): void {
    foreach (['StripeGateway', 'PayPalGateway', 'KlarnaGateway'] as $name) {
        [$response, $code] = $declines[$name];
        same($code, decline('App\\Payments\\Gateways\\' . $name, $response)->declineCode());
    }
});
check('functional', '204-berlin-last-evening', function (): void {
    same(true, validAt('2026-10-31T22:30:00Z', 'Europe/Berlin', '2026-10-31'));
    same(false, validAt('2026-10-31T23:30:00Z', 'Europe/Berlin', '2026-10-31'));
});
check('functional', '205-quotes', function (): void {
    same([['number', 'customer', 'note', 'total'], ['O-1', 'Joe "JJ" Smith', '', '12.50']], rows(csv([order('Joe "JJ" Smith')])));
});

// ---------- completeness: what the issues imply but do not list ----------
check('completeness', '203-sepa-and-giftcard-too', function () use ($declines): void {
    foreach (['SepaGateway', 'GiftCardGateway'] as $name) {
        [$response, $code, $message] = $declines[$name];
        $e = decline('App\\Payments\\Gateways\\' . $name, $response);
        same($code, $e->declineCode());
        same($message, $e->getMessage());
    }
});
check('completeness', '202-remove-address-method', function (): void {
    $calc = new App\Shipping\ShippingCalculator(new CountingProvider());
    $cart = cart();
    $cart->add(new CartItem('mug', 1, 1290, 400));
    $cart->add(new CartItem('parcel', 1, 5000, 20000));
    same(2990, $calc->cost($cart));
    $cart->remove('parcel');
    same(990, $calc->cost($cart));
    $cart->shipping = new Address('DE', '10115');
    same(490, $calc->cost($cart));
    $cart->shippingMethod = 'express';
    same(990, $calc->cost($cart));
    $cart->add(new CartItem('mug', 3, 1290, 400));
    same(1090, $calc->cost($cart));
});
check('completeness', '204-other-timezones-and-dst', function (): void {
    same(true, validAt('2026-11-01T03:30:00Z', 'America/New_York', '2026-10-31'));
    same(false, validAt('2026-11-01T04:30:00Z', 'America/New_York', '2026-10-31'));
    same(true, validAt('2026-10-31T20:59:00Z', 'Europe/Istanbul', '2026-10-31'));
    same(false, validAt('2026-10-31T21:01:00Z', 'Europe/Istanbul', '2026-10-31'));
    same(true, validAt('2026-03-29T21:59:00Z', 'Europe/Berlin', '2026-03-29'));
    same(false, validAt('2026-03-29T22:01:00Z', 'Europe/Berlin', '2026-03-29'));
    same(false, validAt('2026-10-30T23:30:00Z', 'Europe/Berlin', '2026-10-30'));
});
check('completeness', '205-rfc4180-fields', function (): void {
    same([['number', 'customer', 'note', 'total'], ['O-2', 'Müller, Anna', "line one\nline two", '0.99']],
        rows(csv([order('Müller, Anna', "line one\nline two", 99, 'O-2')])));
    same([['number', 'customer', 'note', 'total'], ['O-3', "O'Brien", 'back\\slash "q"', '1.00']],
        rows(csv([order("O'Brien", 'back\\slash "q"', 100, 'O-3')])));
});
check('completeness', '205-formula-injection', function (): void {
    same([
        ['number', 'customer', 'note', 'total'],
        ['O-4', "'=HYPERLINK(\"http://x\",\"click\")", "'+1 555 0100", '5.00'],
        ['O-5', "'@SUM(A1)", "'-2 discount", '5.00'],
        ['O-6', "'\tTabbed", "'\rCR", '5.00'],
    ], rows(csv([
        order('=HYPERLINK("http://x","click")', '+1 555 0100', 500, 'O-4'),
        order('@SUM(A1)', "-2 discount", 500, 'O-5'),
        order("\tTabbed", "\rCR", 500, 'O-6'),
    ])));
});

// ---------- invariants: what must not break ----------
check('invariants', 'filter-override-respected-for-tax', function (): void {
    $filters = new Filters();
    $filters->add('cart_needs_shipping', function (bool $value, Cart $cart): bool {
        return $value || isset($cart->items()['certificate']);
    });
    $cart = cart($filters);
    $cart->add(new CartItem('certificate', 1, 4900, 0, true));
    same('FR', resolver()->resolve($cart)->country);
    $cart->shippingMethod = 'local_pickup';
    same('DE', resolver()->resolve($cart)->country);
    $filters->add('cart_needs_shipping', function (bool $value): bool { return false; });
    $plain = cart($filters, 'local_pickup');
    $plain->add(new CartItem('mug', 1, 1290, 400));
    same('AT', resolver()->resolve($plain)->country);
});
check('invariants', 'filter-override-respected-for-shipping', function (): void {
    $filters = new Filters();
    $filters->add('cart_needs_shipping', function (bool $value): bool { return false; });
    $cart = cart($filters);
    $cart->add(new CartItem('mug', 1, 1290, 400));
    same(0, (new App\Shipping\ShippingCalculator(new CountingProvider()))->cost($cart));
});
check('invariants', 'carrier-called-once-per-package', function (): void {
    $provider = new CountingProvider();
    $calc = new App\Shipping\ShippingCalculator($provider);
    $cart = cart();
    $cart->add(new CartItem('mug', 1, 1290, 400));
    for ($i = 0; $i < 5; $i++) {
        $calc->cost($cart);
    }
    $cart->add(new CartItem('parcel', 1, 5000, 20000));
    $calc->cost($cart);
    $calc->cost($cart);
    $cart->remove('parcel');
    $calc->cost($cart);
    same(2, $provider->calls);
});
check('invariants', 'csv-amounts-unchanged', function (): void {
    same([['number', 'customer', 'note', 'total'], ['C-9', 'Ann', 'refund', '-12.50']], rows(csv([order('Ann', 'refund', -1250, 'C-9')])));
});
check('invariants', 'payments-and-messages', function () use ($declines): void {
    $ok = new App\Payments\Gateways\SepaGateway(function (array $r): array { return ['state' => 'confirmed', 'id' => 'mandate-1']; });
    same('mandate-1', $ok->charge(500, 'iban'));
    $e = decline('App\\Payments\\Gateways\\PayPalGateway', $declines['PayPalGateway'][0]);
    same('The instrument was declined.', $e->getMessage());
    $missing = decline('App\\Payments\\Gateways\\StripeGateway', ['status' => 'failed']);
    same('unknown', $missing->declineCode());
    same(true, $missing instanceof App\Exception\DomainError);
});

echo json_encode($results), PHP_EOL;
