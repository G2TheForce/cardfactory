<?php

namespace App\Console\Commands;

use App\Models\DigitalProfile;
use App\Services\Social\SocialStatsService;
use Illuminate\Console\Command;

class RefreshSocialCounts extends Command
{
    protected $signature = 'profiles:refresh-social-counts';

    protected $description = 'Fetch follower and subscriber counts for the social links on live digital profiles';

    public function handle(SocialStatsService $stats): int
    {
        $count = 0;
        DigitalProfile::where('is_active', true)
            ->whereHas('customer', fn ($q) => $q->whereNotNull('social_links'))
            ->with('customer')
            ->chunkById(100, function ($profiles) use ($stats, &$count) {
                foreach ($profiles as $profile) {
                    $stats->refresh($profile);
                    $count++;
                }
            });

        $this->info("Refreshed social counts for {$count} profile(s).");

        return self::SUCCESS;
    }
}
