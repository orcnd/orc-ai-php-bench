<?php
declare(strict_types=1);
namespace App\Billing;

use App\Support\Clock;

final class CreditsService
{
    private ?Clock $clock;

    public function __construct(?Clock $clock = null)
    {
        $this->clock = $clock;
    }

    public function label(?string $prefix = null, int $number = 0): string
    {
        return ($prefix ?? 'Cre') . '-' . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function hasClock(): bool
    {
        return $this->clock !== null;
    }
}
