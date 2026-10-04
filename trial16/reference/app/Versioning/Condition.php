<?php
declare(strict_types=1);
namespace App\Versioning;

/** Evaluates feature-rollout conditions like "'2.10' >= '2.9'" from tenant configuration. */
final class Condition
{
    public function evaluate(string $left, string $operator, string $right): bool
    {
        $a = null;
        $b = null;
        try {
            $versions = Version::tryParse($left, $a) && Version::tryParse($right, $b) && $a !== null && $b !== null;
        } catch (InvalidVersion $malformed) {
            $versions = false;
        }
        $cmp = $versions && $a !== null && $b !== null ? $a->compare($b) : strcmp($left, $right);
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
