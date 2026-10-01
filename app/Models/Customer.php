<?php

declare(strict_types=1);

namespace App\Models;

final class Customer
{
    private bool $vip;

    public function __construct(bool $vip)
    {
        $this->vip = $vip;
    }

    public function isVip(): bool
    {
        return $this->vip;
    }
}
