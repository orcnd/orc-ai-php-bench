<?php
declare(strict_types=1);
namespace App\Time;

final class TimeEntry
{
    public int $id;
    public int $projectId;
    public string $date;
    public int $minutes;
    /** Hourly rate in cents. */
    public int $rateCents;

    public function __construct(int $id, int $projectId, string $date, int $minutes, int $rateCents)
    {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->date = $date;
        $this->minutes = $minutes;
        $this->rateCents = $rateCents;
    }
}
