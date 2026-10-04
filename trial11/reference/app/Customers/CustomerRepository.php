<?php
declare(strict_types=1);
namespace App\Customers;

use App\Storage\FileStore;

/**
 * Release 5.0 (V2) customer persistence, compatible with V1 nodes:
 * the main document keeps exactly the V1 fields (V1 rewrites it with only
 * those), and V2-only data lives in a sidecar "customer-N.v2". The sidecar
 * records which legacy address it was derived from, so a V1 address change
 * after migration is detected and re-parsed.
 */
final class CustomerRepository
{
    private FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    public function find(int $id): Customer
    {
        $main = $this->store->get('customer-' . $id);
        if (!is_array($main)) {
            throw new \OutOfBoundsException('No customer ' . $id);
        }
        $side = $this->store->get('customer-' . $id . '.v2');
        $side = is_array($side) ? $side : [];
        $address = (string) $main['address'];
        if (($side['address_from'] ?? null) === $address) {
            [$street, $postcode, $city] = [(string) $side['street'], (string) $side['postcode'], (string) $side['city']];
        } else {
            [$street, $postcode, $city] = self::parse($address);
        }
        return new Customer($id, (string) $main['name'], $street, $postcode, $city,
            (int) round((float) $main['monthly_fee'] * 100), (string) ($side['currency'] ?? 'EUR'));
    }

    public function save(Customer $customer): void
    {
        $address = self::compose($customer->street, $customer->postcode, $customer->city);
        $current = $this->store->get('customer-' . $customer->id);
        if (is_array($current) && is_string($current['address'] ?? null)) {
            $existing = $this->find($customer->id);
            // Unchanged structured address: keep the original V1 string verbatim.
            if ([$existing->street, $existing->postcode, $existing->city] === [$customer->street, $customer->postcode, $customer->city]) {
                $address = $current['address'];
            }
        }
        $this->store->put('customer-' . $customer->id . '.v2', [
            'street' => $customer->street, 'postcode' => $customer->postcode, 'city' => $customer->city,
            'currency' => $customer->currency, 'address_from' => $address,
        ]);
        $this->store->put('customer-' . $customer->id, [
            'name' => $customer->name, 'address' => $address, 'monthly_fee' => $customer->feeCents / 100,
        ]);
    }

    /** Re-derive the sidecar from the main document; returns whether it was (re)written. */
    public function refreshSidecar(int $id): bool
    {
        $main = $this->store->get('customer-' . $id);
        $side = $this->store->get('customer-' . $id . '.v2');
        if (!is_array($main) || (is_array($side) && ($side['address_from'] ?? null) === $main['address'])) {
            return false;
        }
        [$street, $postcode, $city] = self::parse((string) $main['address']);
        $this->store->put('customer-' . $id . '.v2', [
            'street' => $street, 'postcode' => $postcode, 'city' => $city,
            'currency' => is_array($side) ? (string) ($side['currency'] ?? 'EUR') : 'EUR',
            'address_from' => (string) $main['address'],
        ]);
        return true;
    }

    /** @return array{0: string, 1: string, 2: string} */
    public static function parse(string $address): array
    {
        $parts = explode(', ', $address);
        $last = (string) array_pop($parts);
        if ($parts !== [] && preg_match('/^(\d{4,5})\s+(.+)$/u', $last, $m) === 1) {
            return [implode(', ', $parts), $m[1], $m[2]];
        }
        return [$address, '', ''];
    }

    public static function compose(string $street, string $postcode, string $city): string
    {
        return $postcode === '' && $city === '' ? $street : $street . ', ' . trim($postcode . ' ' . $city);
    }
}
