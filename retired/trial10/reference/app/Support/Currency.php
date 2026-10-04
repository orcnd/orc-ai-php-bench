<?php
declare(strict_types=1);
namespace App\Support;

final class Currency
{
    /** @var array<string, int> minor units per currency */
    private const DECIMALS = ['EUR' => 2, 'USD' => 2, 'GBP' => 2, 'JPY' => 0, 'KWD' => 3];

    /**
     * Convert an amount in minor units with a decimal rate string ("162.35").
     * Throws when the result is not a whole number of target minor units.
     */
    public function convert(int $amount, string $from, string $to, string $rate): int
    {
        [$numerator, $denominator] = $this->fraction($amount, $from, $to, $rate);
        if ($numerator % $denominator !== 0) {
            throw new \RangeException(sprintf('Converting %d %s to %s needs rounding', $amount, $from, $to));
        }
        return intdiv($numerator, $denominator);
    }

    /** Same as convert(), rounding half up to the target minor unit. */
    public function convertRounded(int $amount, string $from, string $to, string $rate): int
    {
        [$numerator, $denominator] = $this->fraction($amount, $from, $to, $rate);
        return Rounding::halfUp($numerator, $denominator);
    }

    /** @return array{0: int, 1: int} */
    private function fraction(int $amount, string $from, string $to, string $rate): array
    {
        $parts = explode('.', $rate . '.');
        $rateDecimals = strlen($parts[1]);
        $rateInt = (int) ($parts[0] . $parts[1]);
        $shift = self::DECIMALS[$to] - self::DECIMALS[$from] - $rateDecimals;
        $numerator = $amount * $rateInt;
        $denominator = 1;
        if ($shift >= 0) {
            $numerator *= 10 ** $shift;
        } else {
            $denominator = 10 ** -$shift;
        }
        return [$numerator, $denominator];
    }
}
