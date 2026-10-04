<?php
declare(strict_types=1);
namespace App\Config;

/**
 * Merges tenant overrides onto the base configuration.
 *
 * Semantics (relied on by the deploy tooling):
 * - a key whose override value is null is "not set": the base value stays;
 * - any other override value replaces the base value, including false, 0, '' and [];
 * - maps (string-keyed arrays) are merged recursively, lists (0..n keyed arrays) are replaced as a whole.
 */
final class Merger
{
    /**
     * @param array<mixed> $base
     * @param array<mixed> $override
     * @return array<mixed>
     */
    public function merge(array $base, array $override): array
    {
        $result = $base;
        foreach ($override as $key => $value) {
            if (empty($value)) {
                continue;
            }
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $result[$key] = array_merge_recursive($base[$key], $value);
                continue;
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
