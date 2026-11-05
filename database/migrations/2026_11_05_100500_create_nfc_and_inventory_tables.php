<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product catalogue line (one per SKU). Physical units live in nfc_cards.
        Schema::create('card_inventory', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->string('tag_type', 20)->index(); // NTAG213 | NTAG215 | NTAG216 | MIFARE | OTHER
            $table->string('material', 40)->nullable(); // PVC, metal, wood, ...
            $table->text('description')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nfc_cards', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('card_inventory_id')->nullable()->constrained('card_inventory')->nullOnDelete();
            $table->string('card_identifier', 32)->unique(); // printed / public id, e.g. SC-7K2M9Q
            $table->string('uid', 32)->nullable()->unique(); // hardware tag UID (hex), when known
            $table->string('tag_type', 20)->default('NTAG215');
            $table->string('status', 20)->default('in_stock')->index();
            // in_stock | assigned | active | sold | defective | lost | replaced | retired
            $table->string('batch_number', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('encoded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nfc_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nfc_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('digital_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_assignment_id')->nullable()->constrained('nfc_assignments')->nullOnDelete();
            $table->string('status', 20)->default('assigned')->index(); // assigned | active | inactive | replaced | revoked
            $table->string('profile_url', 1000)->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['nfc_card_id', 'status']);
        });

        Schema::create('card_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('order_number', 32)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending | paid | processing | shipped | completed | cancelled
            $table->json('items')->nullable(); // [{card_inventory_id, sku, name, qty, unit_price}]
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('paid_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('card_order_nfc_card', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nfc_card_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['card_order_id', 'nfc_card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_order_nfc_card');
        Schema::dropIfExists('card_orders');
        Schema::dropIfExists('nfc_assignments');
        Schema::dropIfExists('nfc_cards');
        Schema::dropIfExists('card_inventory');
    }
};
