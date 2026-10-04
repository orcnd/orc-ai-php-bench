<?php
declare(strict_types=1);
namespace App\Net;

/** Live DNS queries; 20-300 ms per call, nothing is cached. */
final class SystemDnsResolver implements DnsResolver
{
    public function acceptsMail(string $domain): bool
    {
        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }
}
