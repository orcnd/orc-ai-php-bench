<?php
declare(strict_types=1);
namespace App\Reports;

use App\Invoices\Invoice;

final class Paginator
{
    /**
     * One page of the invoice list, ordered by due date (oldest first), then
     * by invoice id.
     *
     * @param list<Invoice> $invoices
     * @return list<Invoice>
     */
    public function page(array $invoices, int $page, int $perPage): array
    {
        usort($invoices, function (Invoice $a, Invoice $b): int {
            return [$a->dueDate, $a->id] <=> [$b->dueDate, $b->id];
        });
        return array_slice($invoices, ($page - 1) * $perPage, $perPage);
    }
}
