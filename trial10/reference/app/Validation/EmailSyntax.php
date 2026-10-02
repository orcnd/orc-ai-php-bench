<?php
declare(strict_types=1);
namespace App\Validation;

/** Syntax check of an e-mail address (pure function, no I/O). */
final class EmailSyntax
{
    private const PATTERN = '/^[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$/';

    public static function isValid(string $email): bool
    {
        return strlen($email) <= 254 && preg_match(self::PATTERN, $email) === 1;
    }
}
