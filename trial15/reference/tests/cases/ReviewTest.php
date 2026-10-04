<?php
declare(strict_types=1);

use App\Cart\Address;
use App\Cart\Cart;
use App\Cart\CartItem;
use App\Hooks\Filters;

final class ReviewRates implements App\Shipping\RateProvider
{
    public int $calls = 0;
    public function quote(string $method, Address $destination, array $package): int
    {
        $this->calls++;
        return count($package) * 100 + ($destination->country === 'DE' ? 0 : 1000);
    }
}
function reviewCart(?Filters $filters = null): Cart
{
    $cart = new Cart($filters ?? new Filters(), new Address('AT', '1010'));
    $cart->shipping = new Address('FR', '75001');
    return $cart;
}

test('local pickup is taxed at the store unless nothing is shipped', function (): void {
    $resolver = new App\Tax\TaxLocationResolver(new Address('DE', '80331'));
    $cart = reviewCart();
    $cart->shippingMethod = 'local_pickup';
    $cart->add(new CartItem('mug', 1, 100, 400));
    assertSame('DE', $resolver->resolve($cart)->country);
    $filters = new Filters();
    $filters->add('cart_needs_shipping', function (): bool { return false; });
    $digital = reviewCart($filters);
    $digital->shippingMethod = 'local_pickup';
    $digital->add(new CartItem('mug', 1, 100, 400));
    assertSame('AT', $resolver->resolve($digital)->country);
});
test('shipping is quoted once per distinct package and destination', function (): void {
    $rates = new ReviewRates();
    $calc = new App\Shipping\ShippingCalculator($rates);
    $cart = reviewCart();
    $cart->add(new CartItem('a', 1, 100, 400));
    assertSame(1100, $calc->cost($cart));
    assertSame(1100, $calc->cost($cart));
    $cart->add(new CartItem('b', 1, 100, 400));
    assertSame(1200, $calc->cost($cart));
    $cart->shipping = new Address('DE', '10115');
    assertSame(200, $calc->cost($cart));
    assertSame(3, $rates->calls);
});
test('every gateway keeps the decline code', function (): void {
    foreach ([
        ['StripeGateway', ['status' => 'failed', 'decline_code' => 'c1']],
        ['SepaGateway', ['state' => 'rejected', 'reason_code' => 'AM04']],
        ['GiftCardGateway', ['result' => 'error', 'status_code' => 'expired']],
    ] as [$class, $response]) {
        $name = 'App\\Payments\\Gateways\\' . $class;
        $gateway = new $name(function (array $r) use ($response): array { return $response; });
        try {
            $gateway->charge(1, 't');
            throw new RuntimeException('no decline');
        } catch (App\Exception\PaymentFailed $e) {
            assertSame(array_values(array_slice($response, 1))[0], $e->declineCode());
        }
    }
});
test('coupons expire at midnight store time', function (): void {
    $validator = new App\Coupons\CouponValidator(new App\Support\FrozenClock('2026-11-01T01:00:00Z'), 'America/New_York');
    assertSame(true, $validator->isValid(new App\Coupons\Coupon('X', '2026-10-31')));
});
test('csv quoting and formula neutralisation', function (): void {
    $csv = (new App\Export\OrderCsv())->render([['number' => 'O', 'customer' => '=1+1', 'note' => "a\nb", 'total_cents' => -5]]);
    assertSame("number,customer,note,total\r\nO,'=1+1,\"a\nb\",-0.05\r\n", $csv);
});
