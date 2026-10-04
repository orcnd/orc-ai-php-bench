<?php
declare(strict_types=1);
namespace App\Cart;

use App\Hooks\Filters;

final class Cart
{
    /** @var array<string, CartItem> */
    private array $items = [];
    public Address $billing;
    public ?Address $shipping = null;
    /** "flat_rate", "express" or "local_pickup" */
    public string $shippingMethod = 'flat_rate';
    private Filters $filters;

    public function __construct(Filters $filters, Address $billing)
    {
        $this->filters = $filters;
        $this->billing = $billing;
    }

    public function add(CartItem $item): void
    {
        $this->items[$item->sku] = $item;
    }

    public function remove(string $sku): void
    {
        unset($this->items[$sku]);
    }

    /** @return array<string, CartItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** Whether the order is shipped. Plugins can override this via the "cart_needs_shipping" filter. */
    public function needsShipping(): bool
    {
        $physical = false;
        foreach ($this->items as $item) {
            $physical = $physical || !$item->virtual;
        }
        return (bool) $this->filters->apply('cart_needs_shipping', $physical, $this);
    }
}
