<?php
declare(strict_types=1);
namespace App\Validation;

/**
 * Validates request DTOs: property rules first, then the object's own
 * cross-field validate() if it has one. Mirrors the semantics of the old
 * Symfony-1 validator the mobile apps were written against.
 */
final class EntityValidator
{
    /**
     * @param array<string, callable(mixed): ?string> $rules property => rule returning an error or null
     * @return array<string, string> errors by property ("" for object-level)
     */
    public function validate(object $entity, array $rules): array
    {
        $errors = [];
        foreach ($rules as $property => $rule) {
            $error = $rule($entity->$property ?? null);
            if ($error !== null) {
                $errors[$property] = $error;
            }
        }
        if ($errors === [] && method_exists($entity, 'validate')) {
            $error = $entity->validate();
            if (is_string($error)) {
                $errors[''] = $error;
            }
        }
        return $errors;
    }
}
