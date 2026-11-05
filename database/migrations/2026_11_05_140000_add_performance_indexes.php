<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for the busiest list, filter and report queries. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            // Per-profile reports filter by profile and date range first.
            $table->index(['digital_profile_id', 'occurred_at'], 'analytics_events_profile_time_index');
        });
        Schema::table('nfc_cards', function (Blueprint $table) {
            $table->index('batch_number');
        });
        Schema::table('card_designs', function (Blueprint $table) {
            $table->index(['status', 'updated_at']);
            $table->index('updated_at');
        });
        Schema::table('card_exports', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', fn (Blueprint $table) => $table->dropIndex('analytics_events_profile_time_index'));
        Schema::table('nfc_cards', fn (Blueprint $table) => $table->dropIndex(['batch_number']));
        Schema::table('card_designs', function (Blueprint $table) {
            $table->dropIndex(['status', 'updated_at']);
            $table->dropIndex(['updated_at']);
        });
        Schema::table('card_exports', fn (Blueprint $table) => $table->dropIndex(['status', 'created_at']));
    }
};
