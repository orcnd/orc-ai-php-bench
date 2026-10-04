<?php
declare(strict_types=1);
namespace App\Ledger;

/**
 * Accounting journal. Posted entries are immutable (GoBD / audit trail, see
 * docs/LEGAL.md): a correction posts a reversal of the original entry and a
 * new entry with the corrected amount.
 */
final class Journal
{
    /** @var list<array{id: int, account: string, amount: int, memo: string, reverses: ?int}> */
    private array $entries = [];

    public function post(string $account, int $amount, string $memo): int
    {
        $id = count($this->entries) + 1;
        $this->entries[] = ['id' => $id, 'account' => $account, 'amount' => $amount, 'memo' => $memo, 'reverses' => null];
        return $id;
    }

    /** Correct the amount of a posted entry. Returns the id of the corrected entry. */
    public function correct(int $id, int $newAmount): int
    {
        $original = $this->entry($id);
        $reversal = count($this->entries) + 1;
        $this->entries[] = [
            'id' => $reversal,
            'account' => $original['account'],
            'amount' => -$original['amount'],
            'memo' => 'Reversal of #' . $id,
            'reverses' => $id,
        ];
        return $this->post($original['account'], $newAmount, $original['memo']);
    }

    /** @return array{id: int, account: string, amount: int, memo: string, reverses: ?int} */
    public function entry(int $id): array
    {
        foreach ($this->entries as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }
        throw new \OutOfBoundsException('No entry ' . $id);
    }

    /** @return list<array{id: int, account: string, amount: int, memo: string, reverses: ?int}> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function balance(string $account): int
    {
        $sum = 0;
        foreach ($this->entries as $entry) {
            if ($entry['account'] === $account) {
                $sum += $entry['amount'];
            }
        }
        return $sum;
    }
}
