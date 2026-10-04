<?php
declare(strict_types=1);
namespace App\Reports;

use App\Time\TimeEntry;

final class WeeklyReport
{
    /**
     * Minutes per week. Keys are ISO-8601 week labels ("2026-W01") built from
     * the ISO week-numbering year ('o'), which differs from the calendar year
     * ('Y') around New Year. The payroll provider and the customers' own
     * systems use ISO weeks (docs/REPORTS.md).
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
