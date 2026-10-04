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
        $legacy = rtrim($env['HOME'] ?? '', '/') . '/.acmerc';
        $xdg = $env['XDG_CONFIG_HOME'] ?? '';
        // XDG spec: only absolute paths count. Existing legacy configs keep working.
        if ($xdg === '' || $xdg[0] !== '/' || $exists($legacy)) {
            return $legacy;
        }
        return rtrim($xdg, '/') . '/acme/config';
    }
}
