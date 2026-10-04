<?php
declare(strict_types=1);
namespace App\Versioning;

/** Evaluates feature-rollout conditions like "'2.10' >= '2.9'" from tenant configuration. */
final class Condition
{
    public function evaluate(string $left, string $operator, string $right): bool
    {
        if (Version::tryParse($left, $a) && Version::tryParse($right, $b) && $a !== null && $b !== null) {
            $cmp = $a->compare($b);
        } else {
            $cmp = strcmp($left, $right);
        }
        switch ($operator) {
            case '==': return $cmp === 0;
            case '!=': return $cmp !== 0;
            case '<': return $cmp < 0;
            case '<=': return $cmp <= 0;
            case '>': return $cmp > 0;
            case '>=': return $cmp >= 0;
        }
        throw new \InvalidArgumentException('Unknown operator ' . $operator);
    }
}
