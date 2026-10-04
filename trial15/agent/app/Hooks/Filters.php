<?php
declare(strict_types=1);
namespace App\Hooks;

/**
 * Extension points for plugins (e.g. the "digital goods with physical
 * certificate" plugin forces shipping). Core code must always go through
 * apply() for a filtered value instead of computing it itself.
 */
final class Filters
{
    /** @var array<string, list<callable>> */
    private array $callbacks = [];

    public function add(string $name, callable $callback): void
    {
        $this->callbacks[$name][] = $callback;
    }

    /**
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    public function apply(string $name, $value, ...$args)
    {
        foreach ($this->callbacks[$name] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }
        return $value;
    }
}
