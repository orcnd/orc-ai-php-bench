<?php
declare(strict_types=1);
namespace App\Migration;

use App\Storage\FileStore;

/** bin/migrate-customers: converts existing customer documents for release 5.0. */
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
        throw new \LogicException('TODO');
    }
}
