<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_kits', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->default('#111827');
            $table->string('secondary_color', 9)->default('#6B7280');
            $table->string('accent_color', 9)->default('#4F46E5');
            $table->json('typography')->nullable();       // {heading: {family, weight}, body: {...}}
            $table->json('background')->nullable();       // {type: solid|gradient|image, ...}
            $table->json('company_details')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('card_themes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('card_themes')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 60)->index();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('status', 20)->default('draft')->index(); // draft | published
            $table->boolean('is_premium')->default(false);
            $table->decimal('width_mm', 6, 2)->default(85.60);
            $table->decimal('height_mm', 6, 2)->default(54.00);
            $table->string('orientation', 10)->default('landscape');
            $table->json('palette')->nullable();
            $table->json('fonts')->nullable();
            $table->json('front')->nullable();   // editable scene: background + elements
            $table->json('back')->nullable();
            $table->json('layout')->nullable();  // bleed, safe area, grid, corner radius
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('card_theme_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_theme_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'card_theme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_theme_favorites');
        Schema::dropIfExists('card_themes');
        Schema::dropIfExists('brand_kits');
    }
};
