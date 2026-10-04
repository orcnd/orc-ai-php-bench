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
        $warnings = [];
        foreach ($schema['relationships'] ?? [] as $name) {
            if (in_array(strtolower($name), self::RESERVED, true)) {
                // New rule: a warning unless strict, so existing schemas keep loading (docs/COMPATIBILITY.md).
                $message = sprintf('Relationship "%s" uses a reserved word', $name);
                if ($strict) {
                    $errors[] = $message;
                } else {
                    $warnings[] = $message;
                }
            }
        }
        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
