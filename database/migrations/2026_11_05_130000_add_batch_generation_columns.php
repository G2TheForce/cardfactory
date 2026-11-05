<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_imports', function (Blueprint $table) {
            $table->foreignId('card_design_id')->nullable()->after('card_theme_id')->constrained()->nullOnDelete();
            $table->json('headers')->nullable()->after('file_path');
            $table->unsignedInteger('processed_rows')->default(0)->after('failed_rows');
            $table->unsignedInteger('generated_rows')->default(0)->after('processed_rows');
            $table->string('file_path')->nullable()->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('customer_import_id')->nullable()->after('assigned_designer_id')->constrained()->nullOnDelete();
        });

        Schema::table('card_designs', function (Blueprint $table) {
            $table->foreignId('customer_import_id')->nullable()->after('card_theme_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('card_designs', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_import_id'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_import_id'));
        Schema::table('customer_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_design_id');
            $table->dropColumn(['headers', 'processed_rows', 'generated_rows']);
        });
    }
};
