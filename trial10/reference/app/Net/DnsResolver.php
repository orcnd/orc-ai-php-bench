<?php
declare(strict_types=1);
namespace App\Net;

interface DnsResolver
{
    /** Whether the domain publishes an MX record (or an A record as fallback). */
    public function acceptsMail(string $domain): bool;
}
