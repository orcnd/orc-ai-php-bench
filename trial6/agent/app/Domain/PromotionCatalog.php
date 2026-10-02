<?php
declare(strict_types=1);
namespace App\Domain;

/**
 * Marketing-owned catalogue. Campaign values are edited live and campaigns
 * are retired when they end.
 */
final class PromotionCatalog
{
    /** @var array<string, Promotion> */
    private array $promotions = [];

    public function add(Promotion $promotion): void
    {
        $this->promotions[$promotion->code] = $promotion;
    }

    public function find(string $code): ?Promotion
    {
        return $this->promotions[$code] ?? null;
    }

    public function changeValue(string $code, int $value): void
    {
        $promotion = $this->find($code);
        if ($promotion === null) {
            throw new \OutOfBoundsException('No promotion ' . $code);
        }
        Promotion::validate($promotion->kind, $value);
        $promotion->value = $value;
    }

    public function retire(string $code): void
    {
        unset($this->promotions[$code]);
    }
}
