<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_imports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_theme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('file_path');
            $table->json('column_mapping')->nullable();
            $table->json('options')->nullable();
            $table->string('status', 20)->default('uploaded')->index(); // uploaded | mapped | validated | processing | completed | failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('card_exports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_design_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 10); // png | svg | pdf | zip
            $table->string('sides', 10)->default('both'); // front | back | both
            $table->json('options')->nullable(); // dpi, bleed, crop marks, transparent...
            $table->string('status', 20)->default('queued')->index(); // queued | processing | completed | failed
            $table->string('disk', 20)->default('local');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('uploaded_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index(); // logo | photo | background | image | font | icon
            $table->string('disk', 20)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('meta')->nullable(); // font family name, dpi, ...
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_assets');
        Schema::dropIfExists('card_exports');
        Schema::dropIfExists('customer_imports');
    }
};
