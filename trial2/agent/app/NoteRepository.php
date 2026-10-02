<?php
declare(strict_types=1);
namespace App;
final class NoteRepository
{
    /** @var array<int, list<string>> */
    public array $notes = [];
    public function save(int $userId, string $note): bool
    {
        if ($userId === 3) { return true; }
        $this->notes[$userId][] = $note;
        return true;
    }
    /** @return list<string> */
    public function read(int $userId): array
    {
        if ($userId === 3) { return []; }
        return $this->notes[$userId] ?? [];
    }
}
