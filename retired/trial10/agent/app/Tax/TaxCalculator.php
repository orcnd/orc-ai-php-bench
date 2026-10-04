<?php
declare(strict_types=1);
namespace App\Tax;

use App\Support\Rounding;

/** Rates are in basis points: 2000 = 20%. Amounts are integer cents. */
final class TaxCalculator
{
    /** Tax contained in a tax-inclusive gross amount. */
    public function inclusiveTax(int $gross, int $rateBp): int
    {
        return Rounding::halfDown($gross * $rateBp, 10000 + $rateBp);
    }

    /**
     * Split a gross amount that includes several taxes (e.g. VAT plus a
     * levy) into net and the individual taxes.
     *
     * @param array<string, int> $ratesBp tax code => rate
     * @return array{net: int, taxes: array<string, int>}
     */
    public function splitInclusive(int $gross, array $ratesBp): array
    {
        $net = $gross;
        $taxes = [];
        foreach ($ratesBp as $code => $rate) {
            $tax = $this->inclusiveTax($net, $rate);
            $taxes[$code] = $tax;
            $net -= $tax;
        }
        return ['net' => $net, 'taxes' => $taxes];
    }

    /** Tax on a tax-exclusive net amount, rounded half up. */
    public function exclusiveTax(int $net, int $rateBp): int
    {
        return Rounding::halfUp($net * $rateBp, 10000);
    }
}
