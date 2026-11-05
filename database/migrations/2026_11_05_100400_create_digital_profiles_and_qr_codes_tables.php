<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_design_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('profile_theme', 60)->default('classic');
            $table->json('layout')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('digital_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('card_design_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 32)->unique(); // short public token used in /q/{code}
            $table->string('target_url', 1000);
            $table->json('style')->nullable(); // colors, margin, error correction
            $table->unsignedBigInteger('scans_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('digital_profiles');
    }
};
