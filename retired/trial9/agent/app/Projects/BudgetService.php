<?php
declare(strict_types=1);
namespace App\Projects;

use App\Billing\InvoiceLineFactory;
use App\Time\TimeEntry;

final class BudgetService
{
    private InvoiceLineFactory $lines;

    public function __construct(InvoiceLineFactory $lines)
    {
        $this->lines = $lines;
    }

    /**
     * Money left on the project: budget minus what has been (or will be)
     * invoiced for its time entries.
     *
     * @param list<TimeEntry> $entries
     */
    public function remainingCents(Project $project, array $entries): int
    {
        $billed = 0;
        foreach ($entries as $entry) {
            if ($entry->projectId === $project->id) {
                $billed += $this->lines->fromTimeEntry($entry)->totalCents;
            }
        }
        return $project->timeBudgetMinutes - $billed;
    }

    /** @param list<TimeEntry> $entries */
    public function remainingMinutes(Project $project, array $entries): int
    {
        $used = 0;
        foreach ($entries as $entry) {
            if ($entry->projectId === $project->id) {
                $used += $entry->minutes;
            }
        }
        return $project->timeBudgetMinutes - $used;
    }
}
