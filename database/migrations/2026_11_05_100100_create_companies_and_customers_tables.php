<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // Login account of the customer (role "customer"), when they have one.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_designer_id')->nullable()->constrained('users')->nullOnDelete();

            // Personal
            $table->string('full_name');
            $table->string('professional_title')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();

            // Contact
            $table->string('mobile', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('website')->nullable();
            $table->string('office_phone', 40)->nullable();

            // Address
            $table->string('country', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('office_address')->nullable();
            $table->string('map_url', 1000)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Flexible groups
            $table->json('social_links')->nullable();   // {facebook, instagram, linkedin, tiktok, youtube, x, github}
            $table->json('business')->nullable();       // {description, services[], hours[], company_website}
            $table->json('custom_fields')->nullable();  // [{label, value}]
            $table->json('custom_links')->nullable();   // [{label, url}]
            $table->text('introduction')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('booking_url')->nullable();
            $table->json('visibility')->nullable();     // {physical: [fields], digital: [fields]}

            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('companies');
    }
};
