<?php
declare(strict_types=1);

test('versions compare numerically', function (): void {
    assertSame(true, (new App\Versioning\Condition())->evaluate('2.10', '>', '2.9'));
});
test('merging keeps base keys', function (): void {
    assertSame(['a' => 1, 'b' => 2], (new App\Config\Merger())->merge(['a' => 1, 'b' => 1], ['b' => 2]));
});
test('barcode lookup', function (): void {
    $repo = new App\Catalog\ProductRepository();
    $repo->create('Chair', '400123');
    assertSame('Chair', $repo->findByBarcode('400123') === null ? null : $repo->findByBarcode('400123')->name);
});
