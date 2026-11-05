<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Privacy-friendly: no IP address or user agent stored; visitor_hash is a
        // daily-rotating salted hash used only for unique-visitor counts.
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('digital_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('nfc_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 30);
            // profile_view | qr_scan | nfc_tap | vcard_download | click_website | click_whatsapp
            // click_email | click_phone | click_social | click_custom | card_download
            $table->string('source', 10)->nullable(); // qr | nfc | direct | link
            $table->string('target', 60)->nullable(); // e.g. linkedin
            $table->char('country_code', 2)->nullable();
            $table->string('device_type', 10)->nullable(); // mobile | tablet | desktop
            $table->string('referrer_host')->nullable();
            $table->char('visitor_hash', 64)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['event_type', 'occurred_at']);
            $table->index(['digital_profile_id', 'event_type', 'occurred_at']);
            $table->index(['customer_id', 'occurred_at']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 30); // starter | professional | business
            $table->string('status', 20)->default('active')->index(); // trialing | active | past_due | cancelled | expired
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('interval', 10)->default('month'); // month | year
            $table->string('provider', 30)->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index(); // e.g. user.created, role.updated
            $table->nullableMorphs('subject');
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('analytics_events');
    }
};
