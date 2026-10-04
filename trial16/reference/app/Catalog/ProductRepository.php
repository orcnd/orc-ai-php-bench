<?php
declare(strict_types=1);
namespace App\Catalog;

/** Point-of-sale catalogue. Scanned barcodes must identify exactly one active product. */
final class ProductRepository
{
    /** @var array<int, Product> */
    private array $products = [];
    private int $nextId = 1;

    public function create(string $name, string $barcode): Product
    {
        if ($this->findByBarcode($barcode) !== null) {
            throw new DuplicateBarcode('Barcode already taken: ' . $barcode);
        }
        $product = new Product($this->nextId++, $name, $barcode);
        $this->products[$product->id] = $product;
        return $product;
    }

    public function delete(int $id, string $now): void
    {
        $this->products[$id]->deletedAt = $now;
    }

    public function restore(int $id): void
    {
        $product = $this->products[$id];
        if ($product->isTrashed() && $this->findByBarcode($product->barcode) !== null) {
            throw new DuplicateBarcode('Barcode reused by another product: ' . $product->barcode);
        }
        $product->deletedAt = null;
    }

    public function findByBarcode(string $barcode): ?Product
    {
        foreach ($this->products as $product) {
            if (!$product->isTrashed() && $product->barcode === $barcode) {
                return $product;
            }
        }
        return null;
    }
}
