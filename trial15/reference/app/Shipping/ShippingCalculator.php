<?php
declare(strict_types=1);
namespace App\Shipping;

use App\Cart\Cart;

final class ShippingCalculator
{
    private RateProvider $provider;
    /** @var array<string, int> */
    private array $cache = [];

    public function __construct(RateProvider $provider)
    {
        $this->provider = $provider;
    }

    /** Shipping cost of the cart in cents (0 when nothing is shipped). Rates are cached per request. */
    public function cost(Cart $cart): int
    {
        if (!$cart->needsShipping() || $cart->shipping === null) {
            return 0;
        }
        $package = [];
        foreach ($cart->items() as $item) {
            if (!$item->virtual) {
                $package[] = ['sku' => $item->sku, 'quantity' => $item->quantity, 'weight' => $item->weight];
            }
        }
        // One carrier call per distinct (method, destination, package).
        $key = sha1((string) json_encode([$cart->shippingMethod, $cart->shipping->country, $cart->shipping->postcode, $package]));
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->provider->quote($cart->shippingMethod, $cart->shipping, $package);
        }
        return $this->cache[$key];
    }
}
