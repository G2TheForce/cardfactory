<?php

namespace Database\Seeders;

use App\Models\AnalyticsEvent;
use App\Models\CardDesign;
use App\Models\CardExport;
use App\Models\CardInventory;
use App\Models\CardOrder;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\NfcAssignment;
use App\Models\NfcCard;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Realistic sample data so the dashboard and lists have something to show
 * in local development. Safe to run once on an empty database.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Company::exists()) {
            $this->command?->warn('Demo data already present, skipping.');

            return;
        }

        mt_srand(2026);
        $admin = User::where('email', 'admin@smartcard.test')->firstOrFail();
        $designer = User::where('email', 'designer@smartcard.test')->firstOrFail();
        $sales = User::where('email', 'sales@smartcard.test')->firstOrFail();

        $companies = Company::factory()->count(6)->create(['owner_id' => $admin->id]);

        // Customers spread over the last 12 months.
        $customers = collect(range(1, 40))->map(fn () => Customer::factory()->create([
            'company_id' => $companies->random()->id,
            'created_by' => fake()->randomElement([$admin->id, $sales->id]),
            'assigned_designer_id' => $designer->id,
            'created_at' => $this->randomDate(365),
        ]));
        $customers->push(User::where('email', 'customer@smartcard.test')->first()->customerProfile);

        // Inventory products and NFC cards.
        $products = collect([
            ['NFC-PVC-213', 'White PVC card (NTAG213)', 'NTAG213', 'PVC', 9, 2.4],
            ['NFC-PVC-215', 'Matte black PVC card (NTAG215)', 'NTAG215', 'PVC', 14, 3.1],
            ['NFC-MTL-216', 'Metal premium card (NTAG216)', 'NTAG216', 'Metal', 39, 11.5],
        ])->map(fn ($p) => CardInventory::create([
            'sku' => $p[0], 'name' => $p[1], 'tag_type' => $p[2], 'material' => $p[3],
            'unit_price' => $p[4], 'cost_price' => $p[5], 'reorder_level' => 25,
        ]));

        $nfcCards = collect(range(1, 80))->map(function () use ($products) {
            $product = $products->random();

            return NfcCard::factory()->create(['card_inventory_id' => $product->id, 'tag_type' => $product->tag_type]);
        });

        foreach ($customers as $i => $customer) {
            $created = Carbon::parse($customer->created_at);

            // 1-3 designs per customer
            foreach (range(1, mt_rand(1, 3)) as $n) {
                CardDesign::factory()->create([
                    'user_id' => $customer->user_id ?? $designer->id,
                    'designer_id' => $designer->id,
                    'customer_id' => $customer->id,
                    'name' => $customer->full_name.' · '.['Primary', 'Event', 'Minimal'][$n - 1],
                    'created_at' => $created->copy()->addDays(mt_rand(0, 20)),
                ]);
            }

            if ($i % 4 === 3) {
                continue; // some customers have no digital profile yet
            }

            $profile = DigitalProfile::factory()->create([
                'customer_id' => $customer->id,
                'slug' => Str::slug($customer->full_name).'-'.Str::lower(Str::random(4)),
                'is_active' => $i % 9 !== 0,
                'created_at' => $created,
            ]);
            $qr = QrCode::create([
                'digital_profile_id' => $profile->id,
                'code' => Str::lower(Str::random(10)),
                'target_url' => url('/card/'.$profile->slug),
            ]);

            $nfc = null;
            if ($i % 2 === 0 && $card = $nfcCards->firstWhere('status', 'in_stock')) {
                $card->update(['status' => 'active', 'encoded_at' => $created]);
                NfcAssignment::create([
                    'nfc_card_id' => $card->id,
                    'customer_id' => $customer->id,
                    'digital_profile_id' => $profile->id,
                    'assigned_by' => $sales->id,
                    'status' => 'active',
                    'profile_url' => $qr->target_url,
                    'assigned_at' => $created,
                    'activated_at' => $created,
                ]);
                $nfc = $card;
            }

            // Engagement over the last 90 days (no personal visitor data).
            $events = [];
            foreach (range(1, mt_rand(15, 90)) as $_) {
                $type = fake()->randomElement([
                    AnalyticsEvent::PROFILE_VIEW, AnalyticsEvent::PROFILE_VIEW, AnalyticsEvent::PROFILE_VIEW,
                    AnalyticsEvent::QR_SCAN, AnalyticsEvent::QR_SCAN, AnalyticsEvent::NFC_TAP,
                    AnalyticsEvent::VCARD_DOWNLOAD, AnalyticsEvent::CLICK_WHATSAPP, AnalyticsEvent::CLICK_PHONE,
                    AnalyticsEvent::CLICK_EMAIL, AnalyticsEvent::CLICK_WEBSITE, AnalyticsEvent::CLICK_SOCIAL,
                ]);
                $events[] = [
                    'customer_id' => $customer->id,
                    'digital_profile_id' => $profile->id,
                    'qr_code_id' => $type === AnalyticsEvent::QR_SCAN ? $qr->id : null,
                    'nfc_card_id' => $type === AnalyticsEvent::NFC_TAP ? $nfc?->id : null,
                    'event_type' => $type === AnalyticsEvent::NFC_TAP && ! $nfc ? AnalyticsEvent::QR_SCAN : $type,
                    'source' => match ($type) {
                        AnalyticsEvent::QR_SCAN => 'qr', AnalyticsEvent::NFC_TAP => 'nfc', default => 'direct'
                    },
                    'target' => $type === AnalyticsEvent::CLICK_SOCIAL ? fake()->randomElement(['linkedin', 'instagram']) : null,
                    'country_code' => fake()->randomElement(['US', 'GB', 'NP', 'IN', 'AE', 'DE', 'AU']),
                    'device_type' => fake()->randomElement(['mobile', 'mobile', 'mobile', 'desktop', 'tablet']),
                    'visitor_hash' => hash('sha256', Str::random(12)),
                    'occurred_at' => $this->randomDate(90),
                ];
            }
            AnalyticsEvent::insert($events);
            $profile->update(['views_count' => collect($events)->where('event_type', AnalyticsEvent::PROFILE_VIEW)->count()]);
            $qr->update(['scans_count' => collect($events)->where('event_type', AnalyticsEvent::QR_SCAN)->count()]);
        }

        // Orders across the year.
        foreach (range(1, 45) as $_) {
            $paidAt = $this->randomDate(365);
            $status = fake()->randomElement(['paid', 'completed', 'completed', 'shipped', 'pending', 'cancelled']);
            CardOrder::factory()->create([
                'customer_id' => $customers->random()->id,
                'created_by' => $sales->id,
                'status' => $status,
                'paid_at' => in_array($status, CardOrder::REVENUE_STATUSES, true) ? $paidAt : null,
                'created_at' => $paidAt,
            ]);
        }

        // Card exports (downloads).
        CardDesign::inRandomOrder()->limit(30)->get()->each(fn (CardDesign $design) => CardExport::create([
            'user_id' => $design->user_id,
            'card_design_id' => $design->id,
            'format' => fake()->randomElement(['pdf', 'png', 'svg']),
            'status' => 'completed',
            'downloads_count' => mt_rand(1, 6),
            'completed_at' => $design->created_at,
        ]));

        // Subscriptions for customer accounts.
        Subscription::create([
            'user_id' => User::where('email', 'customer@smartcard.test')->value('id'),
            'plan' => 'professional', 'status' => 'active', 'amount' => 12, 'interval' => 'month',
            'starts_at' => now()->subMonths(4),
        ]);
        foreach ($companies as $company) {
            Subscription::create([
                'user_id' => $admin->id,
                'plan' => 'business', 'status' => 'active', 'amount' => 49 * 12 * 0.8, 'interval' => 'year',
                'provider_reference' => 'demo-'.$company->ulid,
                'starts_at' => $this->randomDate(300),
            ]);
        }
    }

    private function randomDate(int $days): Carbon
    {
        return now()->subDays(mt_rand(0, $days))->subMinutes(mt_rand(0, 1439));
    }
}
