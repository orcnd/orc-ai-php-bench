<?php
declare(strict_types=1);
namespace App\Query;

/**
 * Top-N report queries: project columns (some computed by expensive
 * callbacks, e.g. currency conversion via an API), then ORDER BY, then LIMIT.
 */
final class TopN
{
    /** @var int number of computed-column evaluations (exposed for the profiler) */
    public int $computations = 0;

    /**
     * @param list<array<string, int|string>> $rows
     * @param array<string, string|callable(array<string, int|string>): (int|string)> $projection alias => source column or callback
     * @return list<array<string, int|string>>
     */
    public function run(array $rows, array $projection, string $orderBy, bool $descending, int $limit): array
    {
        $projected = [];
        foreach ($rows as $row) {
            $out = [];
            foreach ($projection as $alias => $source) {
                if (is_callable($source)) {
                    $this->computations++;
                    $out[$alias] = $source($row);
                } else {
                    $out[$alias] = $row[$source];
                }
            }
            $projected[] = $out;
        }
        usort($projected, function (array $a, array $b) use ($orderBy, $descending): int {
            return $descending ? $b[$orderBy] <=> $a[$orderBy] : $a[$orderBy] <=> $b[$orderBy];
        });
        return array_slice($projected, 0, $limit);
    }
}
