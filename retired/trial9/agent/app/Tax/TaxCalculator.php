<?php
declare(strict_types=1);
namespace App\Tax;

/** Rates are in basis points: 2000 = 20%. Amounts are integer cents. */
final class TaxCalculator
{
    /** Tax contained in a tax-inclusive gross amount. */
    public function inclusiveTax(int $gross, int $rateBp): int
    {
        $numerator = $gross * $rateBp;
        $denominator = 10000 + $rateBp;
        $tax = intdiv($numerator, $denominator);
        return 2 * ($numerator % $denominator) > $denominator ? $tax + 1 : $tax;
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
        return intdiv($net * $rateBp + 5000, 10000);
    }
}
