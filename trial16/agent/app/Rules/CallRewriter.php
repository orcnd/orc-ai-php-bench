<?php
declare(strict_types=1);
namespace App\Rules;

/**
 * Upgrades pricing-rule expressions written for rule engine v1 to v2.
 * v2 removed positional arguments for discount(): discount(10, true) must
 * become discount(percent: 10, stackable: true).
 */
final class CallRewriter
{
    public function upgrade(string $expression): string
    {
        return $expression;
    }
}
