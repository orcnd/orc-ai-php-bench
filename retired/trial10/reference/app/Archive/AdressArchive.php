<?php
declare(strict_types=1);
namespace App\Archive;

/** Client for ADRESS (see README, glossary). */
final class AdressArchive
{
    public const BUCKET = 'adress-prod-eu1';

    /** @var list<array{kind: string, ref: string, sha256: string}> */
    private static array $stored = [];

    /** Archive a file; returns the ADRESS reference to keep with the record. */
    public function store(string $kind, string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new \RuntimeException('Cannot read ' . $path);
        }
        $ref = sprintf('ADR-%s-%s', strtoupper(substr($kind, 0, 3)), substr($hash, 0, 12));
        self::$stored[] = ['kind' => $kind, 'ref' => $ref, 'sha256' => $hash];
        return $ref;
    }

    /** @return list<array{kind: string, ref: string, sha256: string}> */
    public static function stored(): array
    {
        return self::$stored;
    }
}
