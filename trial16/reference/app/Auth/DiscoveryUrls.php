<?php
declare(strict_types=1);
namespace App\Auth;

/** OAuth protected-resource metadata discovery (RFC 9728), used by the API client SDK. */
final class DiscoveryUrls
{
    /** @return list<string> URLs to try, in order */
    public function candidates(string $resourceUrl, ?string $challengeMetadataUrl = null): array
    {
        if ($challengeMetadataUrl !== null && $challengeMetadataUrl !== '') {
            return [$challengeMetadataUrl];
        }
        $parts = parse_url($resourceUrl);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = rtrim($parts['path'] ?? '', '/');
        $urls = [];
        if ($path !== '') {
            $urls[] = $origin . '/.well-known/oauth-protected-resource' . $path;
        }
        $urls[] = $origin . '/.well-known/oauth-protected-resource';
        return $urls;
    }
}
