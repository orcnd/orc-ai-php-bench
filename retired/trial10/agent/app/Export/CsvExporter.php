<?php
declare(strict_types=1);
namespace App\Export;

use App\Support\Money;

final class CsvExporter
{
    /**
     * Accounting export. Tax column is empty when a row has no tax (null).
     *
     * @param list<array{number: string, net: int, tax: ?int}> $rows
     */
    public function export(array $rows): string
    {
        $lines = ['number;net;tax'];
        foreach ($rows as $row) {
            $tax = $row['tax'] == '' ? '' : Money::format((int) $row['tax']);
            $lines[] = implode(';', [$row['number'], Money::format($row['net']), $tax]);
        }
        return implode("\n", $lines) . "\n";
    }
}

