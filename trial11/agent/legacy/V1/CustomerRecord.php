<?php
declare(strict_types=1);
namespace Legacy\V1;

use App\Storage\FileStore;

/**
 * Release 4.x (V1) customer persistence. This file is a frozen copy of the
 * code running on the V1 nodes; it cannot be changed by this release.
 */
final class CustomerRecord
{
    /** @return array{name: string, address: string, monthly_fee: float} */
    public static function load(FileStore $store, int $id): array
    {
        $data = $store->get('customer-' . $id);
        if (!is_array($data) || !is_string($data['name'] ?? null) || !is_string($data['address'] ?? null)
            || !(is_float($data['monthly_fee'] ?? null) || is_int($data['monthly_fee'] ?? null))) {
            throw new \UnexpectedValueException('Corrupt customer document ' . $id);
        }
        return ['name' => $data['name'], 'address' => $data['address'], 'monthly_fee' => (float) $data['monthly_fee']];
    }

    public static function save(FileStore $store, int $id, string $name, string $address, float $monthlyFee): void
    {
        $store->put('customer-' . $id, ['name' => $name, 'address' => $address, 'monthly_fee' => $monthlyFee]);
    }

    /** Customer self-service "change address" form (V1 nodes). */
    public static function changeAddress(FileStore $store, int $id, string $address): void
    {
        $record = self::load($store, $id);
        self::save($store, $id, $record['name'], $address, $record['monthly_fee']);
    }
}
