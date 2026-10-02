<?php
declare(strict_types=1);
namespace App\Newsletter;

final class SubscriberList
{
    /** @var array<int, array<string, true>> list id => set of lower-cased e-mails */
    private array $lists = [];
    /** @var array<int, list<string>> list id => ADRESS refs of imported files */
    private array $evidence = [];

    public function add(int $listId, string $email): void
    {
        $this->lists[$listId][strtolower($email)] = true;
    }

    public function attachEvidence(int $listId, string $adressRef): void
    {
        $this->evidence[$listId][] = $adressRef;
    }

    public function count(int $listId): int
    {
        return count($this->lists[$listId] ?? []);
    }

    public function has(int $listId, string $email): bool
    {
        return isset($this->lists[$listId][strtolower($email)]);
    }

    /** @return list<string> */
    public function evidence(int $listId): array
    {
        return $this->evidence[$listId] ?? [];
    }
}
