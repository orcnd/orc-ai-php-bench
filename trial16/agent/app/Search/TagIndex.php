<?php
declare(strict_types=1);
namespace App\Search;

/** Multi-valued index from tag to article ids, used by the knowledge-base search. */
final class TagIndex
{
    /** @var array<int, array{id: int, title: string, published: string, tags: list<string>}> */
    private array $articles = [];
    /** @var array<string, list<int>> */
    private array $index = [];

    /** @param list<string> $tags */
    public function add(int $id, string $title, string $published, array $tags): void
    {
        $this->articles[$id] = ['id' => $id, 'title' => $title, 'published' => $published, 'tags' => $tags];
        foreach ($tags as $tag) {
            $this->index[$tag][] = $id;
        }
    }

    /**
     * Articles carrying any of $tags, newest first (ties by id), paginated.
     *
     * @param list<string> $tags
     * @return list<int> article ids
     */
    public function search(array $tags, int $limit, int $offset = 0): array
    {
        $hits = [];
        foreach ($tags as $tag) {
            foreach ($this->index[$tag] ?? [] as $id) {
                $hits[] = $this->articles[$id];
            }
        }
        usort($hits, function (array $a, array $b): int {
            return [$b['published'], $a['id']] <=> [$a['published'], $b['id']];
        });
        return array_column(array_slice($hits, $offset, $limit), 'id');
    }
}
