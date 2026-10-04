<?php
declare(strict_types=1);
namespace App\Accounts;

final class UserRepository
{
    /** @var array<string, array{email: string, name: string}> */
    private array $users = [];

    public function add(string $email, string $name): void
    {
        $this->users[strtolower($email)] = ['email' => $email, 'name' => $name];
    }

    public function exists(string $email): bool
    {
        return isset($this->users[strtolower($email)]);
    }

    public function count(): int
    {
        return count($this->users);
    }
}
