<?php
declare(strict_types=1);
namespace App\Shipping;

use App\Cart\Address;

/** Carrier API (slow, rate limited, billed per call). */
interface RateProvider
{
    /** @param list<array{sku: string, quantity: int, weight: int}> $package */
    public function quote(string $method, Address $destination, array $package): int;
}
