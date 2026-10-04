<?php
declare(strict_types=1);
namespace App\Invoices;

final class Invoice
{
    public int $id;
    public ?string $number = null;
    public int $totalCents;
    public string $dueDate;
    public bool $paid = false;
    public bool $creditNote = false;
    public ?int $creditsInvoiceId = null;

    public function __construct(int $id, int $totalCents, string $dueDate)
    {
        $this->id = $id;
        $this->totalCents = $totalCents;
        $this->dueDate = $dueDate;
    }
}
