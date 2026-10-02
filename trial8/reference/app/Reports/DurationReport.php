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
        $minutes = 0;
        foreach ($entries as $entry) {
            $minutes += $entry->minutes;
        }
        return round($minutes / 60, 2);
    }

    /**
     * @param list<TimeEntry> $entries
     * @return array<int, float> hours per project id
     */
    public function hoursByProject(array $entries): array
    {
        $minutes = [];
        foreach ($entries as $entry) {
            $minutes[$entry->projectId] = ($minutes[$entry->projectId] ?? 0) + $entry->minutes;
        }
        ksort($minutes);
        return array_map(function (int $value): float {
            return round($value / 60, 2);
        }, $minutes);
    }
}
