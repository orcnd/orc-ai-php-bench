<?php
declare(strict_types=1);
namespace App\Versioning;

final class Version
{
    /** @var list<int> */
    public array $parts;

    /** @param list<int> $parts */
    private function __construct(array $parts)
    {
        $this->parts = $parts;
    }

    /**
     * Contract (public, used by plugins and the Condition evaluator):
     * - returns false ONLY when $text is not a version string at all (empty, or not starting with a digit);
     * - throws InvalidVersion when $text looks like a version but is malformed
     *   (more than 4 components, empty or non-numeric component);
     * - otherwise returns true and sets $version.
     */
    public static function tryParse(string $text, ?Version &$version = null): bool
    {
        $text = trim($text);
        if ($text === '' || !ctype_digit($text[0])) {
            return false;
        }
        $parts = explode('.', $text);
        if (count($parts) > 4) {
            throw new InvalidVersion('Too many components: ' . $text);
        }
        foreach ($parts as $part) {
            if ($part === '' || !ctype_digit($part)) {
                throw new InvalidVersion('Bad component in ' . $text);
            }
        }
        $version = new self(array_map('intval', $parts));
        return true;
    }

    public function compare(Version $other): int
    {
        $a = array_pad($this->parts, 4, 0);
        $b = array_pad($other->parts, 4, 0);
        return $a <=> $b;
    }
}
