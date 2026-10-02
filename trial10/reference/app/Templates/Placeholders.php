<?php
declare(strict_types=1);
namespace App\Templates;

/**
 * Replaces placeholders in invoice texts: {MONTH}, {YEAR}, {QUARTER},
 * {QUARTER+n} and {QUARTER-n} (relative quarter, e.g. "services for
 * Q{QUARTER+1}" on recurring invoices issued in advance).
 */
final class Placeholders
{
    public function render(string $text, \DateTimeImmutable $date): string
    {
        $quarter = (int) ceil((int) $date->format('n') / 3);
        $text = (string) preg_replace_callback('/\{QUARTER([+-]\d+)?\}/', function (array $m) use ($quarter): string {
            return (string) (((($quarter - 1 + (int) ($m[1] ?? 0)) % 4) + 4) % 4 + 1);
        }, $text);
        return strtr($text, ['{MONTH}' => $date->format('m'), '{YEAR}' => $date->format('Y')]);
    }
}
