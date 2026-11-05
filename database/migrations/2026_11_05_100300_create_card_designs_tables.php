<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_designs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('card_theme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_kit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('draft')->index(); // draft | final | archived
            $table->decimal('width_mm', 6, 2)->default(85.60);
            $table->decimal('height_mm', 6, 2)->default(54.00);
            $table->decimal('bleed_mm', 4, 2)->default(3.00);
            $table->decimal('corner_radius_mm', 4, 2)->default(0);
            $table->string('orientation', 10)->default('landscape');
            $table->json('front')->nullable();
            $table->json('back')->nullable();
            $table->json('settings')->nullable();
            $table->string('front_thumbnail_path')->nullable();
            $table->string('back_thumbnail_path')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('card_elements', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('card_design_id')->constrained()->cascadeOnDelete();
            $table->string('side', 5); // front | back
            $table->string('type', 30); // text, heading, logo, photo, image, qr, shape, line, icon, social, pattern
            $table->string('name')->nullable();
            $table->json('properties');
            $table->unsignedInteger('z_index')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->index(['card_design_id', 'side', 'z_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_elements');
        Schema::dropIfExists('card_designs');
    }
};
