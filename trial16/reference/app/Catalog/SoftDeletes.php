<?php
declare(strict_types=1);
namespace App\Catalog;

/** Project-wide soft-delete convention (see CONTRIBUTING.md): rows carry deletedAt, never an is_deleted flag. */
trait SoftDeletes
{
    public ?string $deletedAt = null;

    public function isTrashed(): bool
    {
        return $this->deletedAt !== null;
    }
}
