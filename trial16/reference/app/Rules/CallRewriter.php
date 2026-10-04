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
        $out = '';
        $offset = 0;
        while (preg_match('/\bdiscount\(/', $expression, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = (int) $m[0][1];
            $open = $start + strlen('discount(');
            $close = self::matchingParen($expression, $open);
            if ($close === null) {
                break;
            }
            $args = self::splitArgs(substr($expression, $open, $close - $open));
            $out .= substr($expression, $offset, $start - $offset);
            $out .= self::rewrite($args) ?? substr($expression, $start, $close + 1 - $start);
            $offset = $close + 1;
        }
        return $out . substr($expression, $offset);
    }

    /** @param list<string> $args */
    private static function rewrite(array $args): ?string
    {
        if ($args === [] || count($args) > 2) {
            return null;
        }
        foreach ($args as $arg) {
            if (preg_match('/^\s*[A-Za-z_]\w*\s*:(?!:)/', $arg) === 1) {
                return null;
            }
        }
        $names = ['percent', 'stackable'];
        $parts = [];
        foreach ($args as $i => $arg) {
            $parts[] = $names[$i] . ': ' . trim($arg);
        }
        return 'discount(' . implode(', ', $parts) . ')';
    }

    private static function matchingParen(string $s, int $from): ?int
    {
        $depth = 1;
        $quote = null;
        for ($i = $from, $n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];
            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')' && --$depth === 0) {
                return $i;
            }
        }
        return null;
    }

    /** @return list<string> */
    private static function splitArgs(string $inner): array
    {
        if (trim($inner) === '') {
            return [];
        }
        $args = [];
        $depth = 0;
        $quote = null;
        $current = '';
        for ($i = 0, $n = strlen($inner); $i < $n; $i++) {
            $c = $inner[$i];
            if ($quote !== null) {
                if ($c === '\\' && $i + 1 < $n) {
                    $current .= $c . $inner[++$i];
                    continue;
                }
                if ($c === $quote) {
                    $quote = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $args[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $args[] = $current;
        return $args;
    }
}
