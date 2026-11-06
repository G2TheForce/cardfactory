<?php

namespace App\Services\Social;

use App\Models\DigitalProfile;
use App\Support\CustomerFields;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Follower and subscriber counts for the social links on a digital profile.
 *
 * Counts come from each network's official API where one exists and its key is
 * configured (see config/services.php). Networks without a public API (LinkedIn,
 * TikTok) or without a key use the count typed in on the profile's Social tab.
 * Results are stored in the profile's settings so public pages never wait on a
 * third-party API.
 */
class SocialStatsService
{
    /** Networks with an official API we can read counts from. */
    public const SUPPORTED = ['youtube', 'github', 'x', 'instagram', 'facebook'];

    /** Button wording and brand colours per network. */
    public const NETWORKS = [
        'youtube' => ['verb' => 'Subscribe', 'noun' => 'subscribers', 'color' => '#ff0000'],
        'instagram' => ['verb' => 'Follow', 'noun' => 'followers', 'color' => '#e1306c', 'gradient' => 'linear-gradient(45deg, #f58529, #dd2a7b 50%, #8134af)'],
        'tiktok' => ['verb' => 'Follow', 'noun' => 'followers', 'color' => '#000000'],
        'facebook' => ['verb' => 'Follow', 'noun' => 'followers', 'color' => '#1877f2'],
        'x' => ['verb' => 'Follow', 'noun' => 'followers', 'color' => '#000000'],
        'linkedin' => ['verb' => 'Connect', 'noun' => 'followers', 'color' => '#0a66c2'],
        'github' => ['verb' => 'Follow', 'noun' => 'followers', 'color' => '#24292f'],
    ];

    /** Whether counts for this network can be fetched with the current configuration. */
    public function available(string $network): bool
    {
        return match ($network) {
            'youtube' => (bool) config('services.youtube.key'),
            'github' => true,
            'x' => (bool) config('services.x.bearer_token'),
            'instagram' => config('services.meta.access_token') && config('services.meta.instagram_business_id'),
            'facebook' => (bool) config('services.meta.access_token'),
            default => false,
        };
    }

    /** Why counts for this network can't be fetched automatically, or null if they can. */
    public function unavailableReason(string $network): ?string
    {
        if ($this->available($network)) {
            return null;
        }

        return match ($network) {
            'youtube' => 'Add YOUTUBE_API_KEY to .env to fetch this automatically.',
            'x' => 'Add X_BEARER_TOKEN to .env (needs a paid X API plan) to fetch this automatically.',
            'instagram' => 'Add META_ACCESS_TOKEN and INSTAGRAM_BUSINESS_ACCOUNT_ID to .env. Works for business and creator accounts.',
            'facebook' => 'Add META_ACCESS_TOKEN to .env to fetch counts for Facebook pages.',
            default => (CustomerFields::SOCIAL[$network] ?? ucfirst($network)).' has no public API for follower counts. Type the number in.',
        };
    }

    /**
     * Fetch fresh counts for every social link on the profile's customer and
     * store them in the profile settings. Returns the stored counts.
     *
     * @return array<string, array{count: ?int, manual: ?int, fetched_at: ?string, error: ?string}>
     */
    public function refresh(DigitalProfile $profile): array
    {
        $profile->loadMissing('customer');
        $links = array_filter($profile->customer?->social_links ?? []);
        $settings = $profile->settings ?? [];
        $stored = $settings['social_counts'] ?? [];
        $out = [];

        foreach ($links as $network => $url) {
            if (! isset(self::NETWORKS[$network]) || ! is_string($url)) {
                continue;
            }
            $row = ($stored[$network] ?? []) + ['count' => null, 'manual' => null, 'fetched_at' => null, 'error' => null];
            if ($this->available($network)) {
                try {
                    $row['count'] = $this->fetch($network, $url);
                    $row['error'] = $row['count'] === null ? 'Account not found or count is hidden.' : null;
                } catch (\Throwable $e) {
                    Log::info('Social count fetch failed', ['network' => $network, 'profile' => $profile->id, 'error' => $e->getMessage()]);
                    $row['error'] = 'Could not reach '.(CustomerFields::SOCIAL[$network] ?? $network).'. Kept the last count.';
                }
                $row['fetched_at'] = now()->toIso8601String();
            }
            $out[$network] = $row;
        }

        $settings['social_counts'] = $out;
        $profile->settings = $settings;
        $profile->saveQuietly();

        return $out;
    }

    /** Count to show for a network: a typed-in number wins over the fetched one. */
    public static function displayCount(array $counts, string $network): ?int
    {
        $row = $counts[$network] ?? null;

        return $row ? ($row['manual'] ?? $row['count'] ?? null) : null;
    }

    /** 1234 → "1.2K", 2500000 → "2.5M". */
    public static function format(int $n): string
    {
        foreach ([1_000_000_000 => 'B', 1_000_000 => 'M', 1_000 => 'K'] as $size => $suffix) {
            if ($n >= $size) {
                $v = $n / $size;

                return rtrim(rtrim(number_format($v, $v < 100 ? 1 : 0, '.', ''), '0'), '.').$suffix;
            }
        }

        return (string) $n;
    }

    /** Fetch one network's count. Null when the account isn't found or hides it. */
    public function fetch(string $network, string $url): ?int
    {
        $handle = SocialHandle::parse($network, $url);
        if ($handle === null) {
            return null;
        }

        return match ($network) {
            'youtube' => $this->youtube($handle),
            'github' => $this->github($handle['value']),
            'x' => $this->x($handle['value']),
            'instagram' => $this->instagram($handle['value']),
            'facebook' => $this->facebook($handle['value']),
            default => null,
        };
    }

    private function http(): PendingRequest
    {
        return Http::timeout(6)->connectTimeout(4)->acceptJson()->withUserAgent(config('app.name').' social-counts');
    }

    /** @param  array{type: string, value: string}  $handle */
    private function youtube(array $handle): ?int
    {
        $query = ['part' => 'statistics', 'key' => config('services.youtube.key')];
        $query += match ($handle['type']) {
            'id' => ['id' => $handle['value']],
            'username' => ['forUsername' => $handle['value']],
            default => ['forHandle' => '@'.$handle['value']],
        };
        $stats = $this->http()->get('https://www.googleapis.com/youtube/v3/channels', $query)->throw()->json('items.0.statistics');
        if (! $stats || ($stats['hiddenSubscriberCount'] ?? false)) {
            return null;
        }

        return isset($stats['subscriberCount']) ? (int) $stats['subscriberCount'] : null;
    }

    private function github(string $user): ?int
    {
        $request = $this->http();
        if ($token = config('services.github.token')) {
            $request = $request->withToken($token);
        }
        $response = $request->get('https://api.github.com/users/'.rawurlencode($user));

        return $response->notFound() ? null : $response->throw()->json('followers');
    }

    private function x(string $user): ?int
    {
        $response = $this->http()->withToken(config('services.x.bearer_token'))
            ->get('https://api.x.com/2/users/by/username/'.rawurlencode($user), ['user.fields' => 'public_metrics']);

        return $response->notFound() ? null : $response->throw()->json('data.public_metrics.followers_count');
    }

    /** Instagram Graph API business discovery: works for business and creator accounts. */
    private function instagram(string $user): ?int
    {
        $version = config('services.meta.graph_version');
        $response = $this->http()->get("https://graph.facebook.com/{$version}/".config('services.meta.instagram_business_id'), [
            'fields' => 'business_discovery.username('.$user.'){followers_count}',
            'access_token' => config('services.meta.access_token'),
        ]);

        return $response->throw()->json('business_discovery.followers_count');
    }

    private function facebook(string $page): ?int
    {
        $version = config('services.meta.graph_version');
        $response = $this->http()->get("https://graph.facebook.com/{$version}/".rawurlencode($page), [
            'fields' => 'followers_count,fan_count',
            'access_token' => config('services.meta.access_token'),
        ]);
        $data = $response->throw()->json();

        return $data['followers_count'] ?? $data['fan_count'] ?? null;
    }
}
