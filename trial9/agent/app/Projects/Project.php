<?php
declare(strict_types=1);
namespace App\Projects;

final class Project
{
    public int $id;
    public string $name;
    /** Money budget in cents. */
    public int $budgetCents;
    /** Time budget in minutes. */
    public int $timeBudgetMinutes;

    public function __construct(int $id, string $name, int $budgetCents, int $timeBudgetMinutes)
    {
        $this->id = $id;
        $this->name = $name;
        $this->budgetCents = $budgetCents;
        $this->timeBudgetMinutes = $timeBudgetMinutes;
    }
}
