<?php
declare(strict_types=1);
namespace App;
final class OrderRepository
{
    /** @var array<int, array{lines: list<int>, promotion: ?int, shipping: int}> */
    public array $orders = [];
    /** @var array<int, array{merchandise: int, credit: int, discount: int}> */
    public array $snapshots = [];
    /** @var array<int, array{order_id: int, refund_cents: int}> */
    public array $refunds = [];
    /** @var list<array{order_id: int, amount: int}> */
    public array $ledger = [];
}
