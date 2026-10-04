<?php
declare(strict_types=1);
namespace App\Payments\Gateways;

use App\Exception\PaymentFailed;
use App\Payments\Gateway;

/** SEPA direct debit (mandate). Rejections carry "reason_code" (AM04, MD01, ...). */
final class SepaGateway implements Gateway
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
        if (($response['state'] ?? '') !== 'confirmed') {
            throw new PaymentFailed($response['reason_text'] ?? 'Payment declined');
        }
        return $response['id'] ?? '';
    }
}
