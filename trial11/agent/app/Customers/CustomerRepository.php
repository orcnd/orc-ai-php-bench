<?php
declare(strict_types=1);
namespace App\Customers;

use App\Storage\FileStore;

/** Release 5.0 (V2) customer persistence. Started by Jonas before his leave; see TASK.md. */
final class CustomerRepository
{
    private FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    public function find(int $id): Customer
    {
        /** @var array<string, mixed>|null $data */
        $data = $this->store->get('customer-' . $id);
        if ($data === null) {
            throw new \OutOfBoundsException('No customer ' . $id);
        }
        return new Customer(
            $id,
            (string) $data['name'],
            (string) ($data['street'] ?? $data['address']),
            (string) ($data['postcode'] ?? ''),
            (string) ($data['city'] ?? ''),
            (int) ($data['fee_cents'] ?? ((float) $data['monthly_fee'] * 100)),
            (string) ($data['currency'] ?? 'EUR')
        );
    }

    public function save(Customer $customer): void
    {
        $this->store->put('customer-' . $customer->id, [
            'name' => $customer->name,
            'street' => $customer->street,
            'postcode' => $customer->postcode,
            'city' => $customer->city,
            'fee_cents' => $customer->feeCents,
            'currency' => $customer->currency,
        ]);
    }
}
