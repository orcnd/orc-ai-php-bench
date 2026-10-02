<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;

final class ShippingService
{
    public function fee(Customer $customer): int
    {
        $fee = 500;

        if ($customer->isVip()) {
            return 1250;
        }

        return $fee;
    }
}
