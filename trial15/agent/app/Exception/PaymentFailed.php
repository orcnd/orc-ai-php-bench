<?php
declare(strict_types=1);
namespace App\Exception;

final class PaymentFailed extends DomainError
{
    private string $declineCode;

    public function __construct(string $message, string $declineCode = 'unknown')
    {
        parent::__construct($message);
        $this->declineCode = $declineCode;
    }

    /** Machine-readable reason shown to the customer ("insufficient_funds", "expired_card", ...). */
    public function declineCode(): string
    {
        return $this->declineCode;
    }
}
