<?php
declare(strict_types=1);
namespace App\Domain;

final class Promotion
{
    public const PERCENT = 'percent';
    public const FIXED = 'fixed';

    public string $code;
    public string $kind;
    /** Percent (0-100) for percent promotions, cents for fixed promotions. */
    public int $value;

    public function __construct(string $code, string $kind, int $value)
    {
        self::validate($kind, $value);
        $this->code = $code;
        $this->kind = $kind;
        $this->value = $value;
    }

    public static function validate(string $kind, int $value): void
    {
        if ($kind !== self::PERCENT && $kind !== self::FIXED) {
            throw new \InvalidArgumentException('Unknown promotion kind ' . $kind);
        }
        if ($value < 0 || ($kind === self::PERCENT && $value > 100)) {
            throw new \InvalidArgumentException('Promotion value out of range');
        }
    }
}
