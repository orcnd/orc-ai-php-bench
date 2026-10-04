<?php
declare(strict_types=1);
namespace App\Routing;

/**
 * Route origin validation against the ROAs published by our RPKI repository.
 * See docs/RPKI.md.
 */
final class OriginValidator
{
    /** @var list<array{prefix: string, asn: int, maxLength: ?int}> */
    private $roas = [];

    /** @param int|null $maxLength as published in the ROA (may be absent) */
    public function addRoa(string $prefix, int $asn, ?int $maxLength = null): void
    {
        $this->roas[] = ['prefix' => $prefix, 'asn' => $asn, 'maxLength' => $maxLength];
    }

    /** @return string 'valid', 'invalid' or 'not-found' */
    public function validate(string $route, int $originAsn): string
    {
        [$address, $length] = self::split($route);
        $covered = false;
        foreach ($this->roas as $roa) {
            [$roaAddress, $roaLength] = self::split($roa['prefix']);
            $maxLength = $roa['maxLength'] ?? $roaLength;
            if ($maxLength < $roaLength || $maxLength > strlen($roaAddress) * 8) {
                continue; // malformed ROA (RFC 6482): not a candidate at all
            }
            if (strlen($roaAddress) !== strlen($address) || $roaLength > $length) {
                continue;
            }
            if (!self::samePrefix($roaAddress, $address, $roaLength)) {
                continue;
            }
            $covered = true;
            if ($roa['asn'] !== 0 && $roa['asn'] === $originAsn && $length <= $maxLength) {
                return 'valid';
            }
        }
        return $covered ? 'invalid' : 'not-found';
    }

    /** @return array{0: string, 1: int} packed address and prefix length */
    private static function split(string $prefix): array
    {
        $parts = explode('/', $prefix, 2);
        $packed = inet_pton($parts[0]);
        if ($packed === false || count($parts) !== 2 || !ctype_digit($parts[1])) {
            throw new \InvalidArgumentException('Not a prefix: ' . $prefix);
        }
        $length = (int) $parts[1];
        if ($length > strlen($packed) * 8) {
            throw new \InvalidArgumentException('Prefix length out of range: ' . $prefix);
        }
        return [$packed, $length];
    }

    private static function samePrefix(string $a, string $b, int $bits): bool
    {
        $bytes = intdiv($bits, 8);
        if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xff << (8 - $rest)) & 0xff;
        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }
}
