<?php
declare(strict_types=1);
namespace App\Validation;

use App\Net\DnsResolver;

/**
 * Checks that an address is syntactically valid and that its domain can
 * receive mail. Performs one live DNS lookup per call.
 */
final class DeliverableEmail
{
    private DnsResolver $dns;

    public function __construct(DnsResolver $dns)
    {
        $this->dns = $dns;
    }

    public function isDeliverable(string $email): bool
    {
        if (!EmailSyntax::isValid($email)) {
            return false;
        }
        return $this->dns->acceptsMail(substr($email, strrpos($email, '@') + 1));
    }
}
