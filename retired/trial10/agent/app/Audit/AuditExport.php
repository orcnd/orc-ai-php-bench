<?php
declare(strict_types=1);
namespace App\Audit;

use App\Ledger\Journal;

/** Journal export (one CSV row per journal entry). */
final class AuditExport
{
    public function csv(Journal $journal): string
    {
        $out = "id;account;amount;memo;reverses\n";
        foreach ($journal->entries() as $entry) {
            $out .= implode(';', [$entry['id'], $entry['account'], $entry['amount'], $entry['memo'], (string) $entry['reverses']]) . "\n";
        }
        return $out;
    }
}
