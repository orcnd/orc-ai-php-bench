<?php
declare(strict_types=1);
namespace App\Schema;

/** Validates customer-defined data schemas before code generation. */
final class Validator
{
    private const RESERVED = ['class', 'function', 'list', 'array', 'echo', 'print', 'new', 'static', 'self', 'parent'];

    /**
     * @param array{attributes?: list<string>, relationships?: list<string>} $schema
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validate(array $schema, bool $strict = false): array
    {
        $errors = [];
        foreach ($schema['attributes'] ?? [] as $name) {
            if (in_array(strtolower($name), self::RESERVED, true)) {
                $errors[] = sprintf('Attribute "%s" uses a reserved word', $name);
            }
        }
        return ['errors' => $errors, 'warnings' => []];
    }
}
