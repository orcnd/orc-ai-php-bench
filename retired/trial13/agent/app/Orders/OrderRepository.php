<?php
declare(strict_types=1);
namespace App\Orders;

/** Order lookup over the current shop database and the migrated ERP data. */
final class OrderRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $shopRows;
    /** @var array<string, array<string, mixed>> */
    private array $legacyRows;

    /**
     * @param array<string, array<string, mixed>> $shopRows
     * @param array<string, array<string, mixed>> $legacyRows
     */
    public function __construct(array $shopRows, array $legacyRows)
    {
        $this->shopRows = $shopRows;
        $this->legacyRows = $legacyRows;
    }

    public function find(string $number): Order
    {
        if (isset($this->shopRows[$number])) {
            /** @var array{number: string, channel: string, shipping: int, lines: list<array{sku: string, qty: int, unit: int, discount?: int}>} $row */
            $row = $this->shopRows[$number];
            return (new OrderHydrator())->hydrate($row);
        }
        if (isset($this->legacyRows[$number])) {
            /** @var array{order_no: string, freight: int, positions: list<array{article: string, qty: int, total: int, rebate?: int}>} $row */
            $row = $this->legacyRows[$number];
            return (new LegacyOrderHydrator())->hydrate($row);
        }
        throw new \OutOfBoundsException('No order ' . $number);
    }
}
