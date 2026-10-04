<?php
declare(strict_types=1);
namespace App\Storage;

/** Writes row batches into warehouse tables. Modes follow Spark's DataFrameWriter; see docs/STORAGE.md. */
final class TableWriter
{
    /** @var array<string, list<array<string, scalar|null>>> */
    private $tables = [];

    /**
     * @param list<array<string, scalar|null>> $rows
     * @return int rows written
     */
    public function write(string $table, array $rows, string $mode = 'error'): int
    {
        $mode = strtolower($mode);
        $exists = array_key_exists($table, $this->tables);
        if ($mode === 'ignore' && $exists) {
            return 0; // Spark: the save is a no-op; the data is not even looked at
        }
        foreach ($rows as $i => $row) {
            if (!isset($row['id'])) {
                throw new InvalidRow('Row ' . $i . ' has no id');
            }
        }
        switch ($mode) {
            case 'ignore':
            case 'errorifexists':
            case 'error':
                if ($exists) {
                    throw new TableExists($table);
                }
                $this->tables[$table] = $rows;
                break;
            case 'append':
                $this->tables[$table] = array_merge($this->tables[$table] ?? [], $rows);
                break;
            case 'overwrite':
                $this->tables[$table] = $rows;
                break;
            default:
                throw new \InvalidArgumentException('Unknown save mode: ' . $mode);
        }
        return count($rows);
    }

    public function drop(string $table): void
    {
        unset($this->tables[$table]);
    }

    /** @return list<array<string, scalar|null>>|null null when the table does not exist */
    public function read(string $table): ?array
    {
        return $this->tables[$table] ?? null;
    }
}
