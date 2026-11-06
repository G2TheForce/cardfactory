<?php

namespace App\Services\Social;

/**
 * Pulls the account name out of a social profile link, e.g.
 * https://www.youtube.com/@mkbhd → ['type' => 'handle', 'value' => 'mkbhd'].
 * Plain handles ("@name" or "name") are accepted too.
 */
final class SocialHandle
{
    /** @return array{type: string, value: string}|null */
    public static function parse(string $network, string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        // A bare handle rather than a link.
        if (! preg_match('#^https?://#i', $url) && ! str_contains($url, '/')) {
            $value = ltrim($url, '@');

            return self::valid($value) ? ['type' => 'handle', 'value' => $value] : null;
        }

        $parts = parse_url(preg_match('#^https?://#i', $url) ? $url : 'https://'.$url);
        $segments = array_values(array_filter(explode('/', $parts['path'] ?? ''), fn ($s) => $s !== ''));
        if (! $segments) {
            return null;
        }

        $result = match ($network) {
            'youtube' => match (true) {
                str_starts_with($segments[0], '@') => ['type' => 'handle', 'value' => substr($segments[0], 1)],
                $segments[0] === 'channel' && isset($segments[1]) => ['type' => 'id', 'value' => $segments[1]],
                $segments[0] === 'user' && isset($segments[1]) => ['type' => 'username', 'value' => $segments[1]],
                $segments[0] === 'c' && isset($segments[1]) => ['type' => 'handle', 'value' => $segments[1]],
                default => null,
            },
            'facebook' => $segments[0] === 'profile.php'
                ? (preg_match('/(?:^|&)id=(\d+)/', $parts['query'] ?? '', $m) ? ['type' => 'id', 'value' => $m[1]] : null)
                : ['type' => 'handle', 'value' => $segments[0] === 'pages' ? end($segments) : $segments[0]],
            'linkedin' => in_array($segments[0], ['in', 'company'], true) && isset($segments[1]) ? ['type' => $segments[0], 'value' => $segments[1]] : null,
            default => ['type' => 'handle', 'value' => ltrim($segments[0], '@')],
        };

        return $result && self::valid($result['value']) ? $result : null;
    }

    private static function valid(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]{1,100}$/', $value);
    }
}
