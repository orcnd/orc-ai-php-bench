<?php
declare(strict_types=1);
namespace App\Newsletter;

use App\Kernel;
use App\Validation\EmailSyntax;

/** POST /lists/{id}/import (multipart CSV upload, see #126) */
final class SubscriberImportController
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @return array{imported: int, rejected: list<int>} rejected = 1-based data row numbers (header excluded)
     */
    public function import(string $csvPath, int $listId): array
    {
        // Syntax only: a DNS lookup per row cannot finish 150,000 rows in 30 s.
        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open upload');
        }
        $imported = 0;
        $rejected = [];
        $row = 0;
        fgetcsv($handle);
        while (($fields = fgetcsv($handle)) !== false) {
            $row++;
            $email = trim((string) ($fields[0] ?? ''));
            if (!EmailSyntax::isValid($email)) {
                $rejected[] = $row;
                continue;
            }
            $this->kernel->subscribers->add($listId, $email);
            $imported++;
        }
        fclose($handle);
        $this->kernel->subscribers->attachEvidence($listId, $this->kernel->archive->store('subscriber-import', $csvPath));
        return ['imported' => $imported, 'rejected' => $rejected];
    }
}
