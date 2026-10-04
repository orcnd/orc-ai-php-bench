<?php
declare(strict_types=1);
namespace App\Tax;

final class UnitTaxDistributor
{
    /**
     * Tax of each unit of an order line. The line tax is computed once for
     * the line; the cents that do not divide evenly go to the first units.
     *
     * @return list<int>
     */
    public function perUnit(int $lineTax, int $quantity): array
    {
        $base = intdiv($lineTax, $quantity);
        $extra = $lineTax - $base * $quantity;
        $units = [];
        for ($i = 0; $i < $quantity; $i++) {
            $units[] = $base + ($i < $extra ? 1 : 0);
        }
        return $units;
    }
}
