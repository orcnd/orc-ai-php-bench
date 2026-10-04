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
        // Evaluate the sort key for every row (only it can be expensive), keep the top N,
        // then compute the remaining columns for those rows only.
        $keyed = [];
        $orderSource = $projection[$orderBy];
        foreach ($rows as $index => $row) {
            if (is_callable($orderSource)) {
                $this->computations++;
                $key = $orderSource($row);
            } else {
                $key = $row[$orderSource];
            }
            $keyed[] = [$key, $index, $row];
        }
        usort($keyed, function (array $a, array $b) use ($descending): int {
            $cmp = $descending ? $b[0] <=> $a[0] : $a[0] <=> $b[0];
            return $cmp !== 0 ? $cmp : $a[1] <=> $b[1];
        });
        $result = [];
        foreach (array_slice($keyed, 0, $limit) as [$key, , $row]) {
            $out = [];
            foreach ($projection as $alias => $source) {
                if ($alias === $orderBy) {
                    $out[$alias] = $key;
                } elseif (is_callable($source)) {
                    $this->computations++;
                    $out[$alias] = $source($row);
                } else {
                    $out[$alias] = $row[$source];
                }
            }
            $result[] = $out;
        }
        return $result;
    }
}
