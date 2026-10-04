<?php
declare(strict_types=1);
namespace App\Catalog;

final class Product
{
    use SoftDeletes;

    public int $id;
    public string $name;
    public string $barcode;

    public function __construct(int $id, string $name, string $barcode)
    {
        $this->id = $id;
        $this->name = $name;
        $this->barcode = $barcode;
    }
}
