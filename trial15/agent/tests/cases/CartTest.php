<?php
declare(strict_types=1);

use App\Cart\Address;
use App\Cart\Cart;
use App\Cart\CartItem;
use App\Hooks\Filters;

test('cart with a download only does not need shipping', function (): void {
    $cart = new Cart(new Filters(), new Address('DE', '10115'));
    $cart->add(new CartItem('ebook', 1, 999, 0, true));
    assertSame(false, $cart->needsShipping());
});
test('physical goods are shipped to the shipping address for tax', function (): void {
    $cart = new Cart(new Filters(), new Address('DE', '10115'));
    $cart->shipping = new Address('FR', '75001');
    $cart->add(new CartItem('mug', 2, 1290, 400));
    assertSame('FR', (new App\Tax\TaxLocationResolver(new Address('DE', '80331')))->resolve($cart)->country);
});
