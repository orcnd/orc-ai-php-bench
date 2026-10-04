<?php
declare(strict_types=1);
namespace App\Tax;

use App\Cart\Address;
use App\Cart\Cart;

/** Which address determines the VAT rate of a cart (EU place-of-supply rules). */
final class TaxLocationResolver
{
    private Address $storeBase;

    public function __construct(Address $storeBase)
    {
        $this->storeBase = $storeBase;
    }

    public function resolve(Cart $cart): Address
    {
        if ($cart->needsShipping() && $cart->shipping !== null) {
            return $cart->shipping;
        }
        return $cart->billing;
    }

    public function storeBase(): Address
    {
        return $this->storeBase;
    }
}
