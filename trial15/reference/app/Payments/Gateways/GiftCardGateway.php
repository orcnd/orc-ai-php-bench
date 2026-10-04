<?php
declare(strict_types=1);
namespace App\Payments\Gateways;

use App\Exception\PaymentFailed;
use App\Payments\Gateway;

/** Gift cards. Rejections carry "status_code" (expired, insufficient_balance). */
final class GiftCardGateway implements Gateway
{
    /** @var callable(array<string, int|string>): array<string, string> */
    private $client;

    /** @param callable(array<string, int|string>): array<string, string> $client HTTP client of the provider SDK */
    public function __construct(callable $client)
    {
        $this->client = $client;
    }

    public function charge(int $cents, string $token): string
    {
        $response = ($this->client)(['amount' => $cents, 'source' => $token]);
        if (($response['result'] ?? '') !== 'ok') {
            throw new PaymentFailed($response['status_text'] ?? 'Payment declined', $response['status_code'] ?? 'unknown');
        }
        return $response['id'] ?? '';
    }
}
