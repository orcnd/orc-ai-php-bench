<?php
declare(strict_types=1);
namespace App\Http;

use App\Refunds\CancellationService;

final class CancellationController
{
    private CancellationService $service;

    public function __construct(CancellationService $service)
    {
        $this->service = $service;
    }

    /**
     * POST /orders/{id}/cancellations with header Idempotency-Key.
     *
     * @param list<string> $lineIds
     * @return array{order_id: int, refund_cents: int, status: string}
     */
    public function cancel(int $orderId, array $lineIds, string $idempotencyKey): array
    {
        if ($idempotencyKey === '') {
            throw new \InvalidArgumentException('Idempotency-Key header is required');
        }
        return $this->service->cancel($orderId, $lineIds, $idempotencyKey);
    }
}
