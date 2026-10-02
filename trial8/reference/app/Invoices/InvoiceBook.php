<?php
declare(strict_types=1);
namespace App\Invoices;

/**
 * Invoice register. Validated invoices carry gapless sequential numbers
 * (legal requirement, see docs/LEGAL.md). Drafts have no number.
 */
final class InvoiceBook
{
    /** @var array<int, Invoice> */
    private array $invoices = [];
    private int $nextId = 1;
    private int $lastNumber = 0;
    private int $lastCreditNumber = 0;

    public function draft(int $totalCents, string $dueDate): Invoice
    {
        $invoice = new Invoice($this->nextId++, $totalCents, $dueDate);
        $this->invoices[$invoice->id] = $invoice;
        return $invoice;
    }

    public function validate(int $id): Invoice
    {
        $invoice = $this->get($id);
        if ($invoice->number === null) {
            $invoice->number = sprintf('INV-%04d', ++$this->lastNumber);
        }
        return $invoice;
    }

    public function markPaid(int $id): void
    {
        $this->get($id)->paid = true;
    }

    /**
     * Drafts can always be deleted. A validated invoice can only be deleted
     * while it is the most recent number and unpaid (its number is then
     * reused); anything else must be cancelled with a credit note.
     */
    public function delete(int $id): void
    {
        $invoice = $this->get($id);
        if ($invoice->number !== null) {
            if ($invoice->creditNote || $invoice->paid || $invoice->number !== sprintf('INV-%04d', $this->lastNumber)) {
                throw new \LogicException('Validated invoices cannot be deleted; issue a credit note (docs/LEGAL.md)');
            }
            $this->lastNumber--;
        }
        unset($this->invoices[$id]);
    }

    public function creditNote(int $id): Invoice
    {
        $original = $this->get($id);
        if ($original->number === null) {
            throw new \LogicException('Drafts are deleted, not credited');
        }
        $credit = new Invoice($this->nextId++, -$original->totalCents, $original->dueDate);
        $credit->creditNote = true;
        $credit->creditsInvoiceId = $original->id;
        $credit->number = sprintf('CN-%04d', ++$this->lastCreditNumber);
        $this->invoices[$credit->id] = $credit;
        return $credit;
    }

    public function get(int $id): Invoice
    {
        if (!isset($this->invoices[$id])) {
            throw new \OutOfBoundsException('No invoice ' . $id);
        }
        return $this->invoices[$id];
    }

    /** @return list<Invoice> */
    public function all(): array
    {
        return array_values($this->invoices);
    }
}
