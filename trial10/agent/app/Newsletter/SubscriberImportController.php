<?php
declare(strict_types=1);
namespace App\Newsletter;

use App\Kernel;

/** POST /lists/{id}/import (multipart CSV upload, see #126) */
final class SubscriberImportController
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @return array{imported: int, rejected: list<int>} rejected = 1-based data row numbers (header excluded)
     */
    public function import(string $csvPath, int $listId): array
    {
        throw new \LogicException('Not implemented (#126)');
    }
}
