<?php
declare(strict_types=1);
namespace App\Reports;

use App\Time\TimeEntry;

/** Hours worked, shown on the dashboard and in the monthly utilisation export. */
final class DurationReport
{
    /** @param list<TimeEntry> $entries */
    public function totalHours(array $entries): float
    {
        $hours = 0.0;
        foreach ($entries as $entry) {
            $hours += round($entry->minutes / 60, 2);
        }
        return round($hours, 2);
    }

    /**
     * @param list<TimeEntry> $entries
     * @return array<int, float> hours per project id
     */
    public function hoursByProject(array $entries): array
    {
        $hours = [];
        foreach ($entries as $entry) {
            $hours[$entry->projectId] = ($hours[$entry->projectId] ?? 0.0) + round($entry->minutes / 60, 2);
        }
        ksort($hours);
        return array_map(function (float $value): float {
            return round($value, 2);
        }, $hours);
    }
}
