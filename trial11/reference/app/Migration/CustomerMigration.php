<?php
declare(strict_types=1);
namespace App\Migration;

use App\Customers\CustomerRepository;
use App\Storage\FileStore;

/** bin/migrate-customers: builds V2 sidecars; idempotent and restartable. */
final class CustomerMigration
{
    private FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    /** @return int number of customers converted by this run */
    public function run(): int
    {
        $repository = new CustomerRepository($this->store);
        $converted = 0;
        foreach ($this->store->keys('customer-') as $key) {
            if (preg_match('/^customer-(\d+)$/', $key, $m) === 1 && $repository->refreshSidecar((int) $m[1])) {
                $converted++;
            }
        }
        return $converted;
    }
}
