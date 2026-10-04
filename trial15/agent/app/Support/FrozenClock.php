<?php
declare(strict_types=1);
namespace App\Support;

final class FrozenClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(string $iso8601)
    {
        $this->now = new \DateTimeImmutable($iso8601);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
