<?php
declare(strict_types=1);
namespace App\Archive;

/** Subjects (people, places, ...) that transcribers link from pages. See docs/SUBJECTS.md. */
final class SubjectIndex
{
    /** @var array<int, array{collection: int, title: string, category: string}> */
    private $subjects = [];
    /** @var int */
    private $nextId = 1;

    /** Links a subject from a page and returns the subject id. */
    public function link(int $collectionId, string $title, string $category): int
    {
        $id = $this->nextId++;
        $this->subjects[$id] = ['collection' => $collectionId, 'title' => trim($title), 'category' => $category];
        return $id;
    }

    public function title(int $id): string
    {
        return $this->subjects[$id]['title'];
    }

    public function count(int $collectionId): int
    {
        return count(array_filter($this->subjects, function (array $s) use ($collectionId): bool {
            return $s['collection'] === $collectionId;
        }));
    }
}
