<?php
declare(strict_types=1);
namespace App\Config;

/** Locates the CLI tool's user configuration file. */
final class PathResolver
{
    /**
     * @param array<string, string> $env environment variables
     * @param callable(string): bool $exists file existence check
     */
    public function configFile(array $env, callable $exists): string
    {
        return rtrim($env['HOME'] ?? '', '/') . '/.acmerc';
    }
}
