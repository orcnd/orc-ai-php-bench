<?php
declare(strict_types=1);
namespace App\Payments\Gateways;

use App\Exception\PaymentFailed;
use App\Payments\Gateway;

/** Card payments. Declines carry "decline_code". */
final class StripeGateway implements Gateway
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
        if (($response['status'] ?? '') !== 'succeeded') {
            throw new PaymentFailed($response['message'] ?? 'Payment declined', $response['decline_code'] ?? 'unknown');
        }
        return $response['id'] ?? '';
    }
}
