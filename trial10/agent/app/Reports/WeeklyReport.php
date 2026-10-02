<?php
declare(strict_types=1);
namespace App\Reports;

use App\Time\TimeEntry;

final class WeeklyReport
{
    /**
     * Minutes per week, keyed "YYYY-Www".
     *
     * @param list<TimeEntry> $entries
     * @return array<string, int>
     */
    public function minutesPerWeek(array $entries): array
    {
        $weeks = [];
        foreach ($entries as $entry) {
            $key = (new \DateTimeImmutable($entry->date))->format('o-\WW');
            $weeks[$key] = ($weeks[$key] ?? 0) + $entry->minutes;
        }
        ksort($weeks);
        return $weeks;
    }
}
