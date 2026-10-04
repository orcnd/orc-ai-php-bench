<?php
declare(strict_types=1);
namespace App\Payments\Gateways;

use App\Exception\PaymentFailed;
use App\Payments\Gateway;

/** Klarna Payments. Declines carry "error_code". */
final class KlarnaGateway implements Gateway
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
        if (($response['fraud_status'] ?? '') !== 'ACCEPTED') {
            throw new PaymentFailed($response['error_message'] ?? 'Payment declined');
        }
        return $response['id'] ?? '';
    }
}
