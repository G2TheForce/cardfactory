<?php

use App\Support\ProfileCodes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every digital profile gets a short, non-sequential card identifier used in
 * its NFC-friendly URL (/c/{identifier}) and printed on cards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_profiles', function (Blueprint $table) {
            $table->string('card_identifier', 16)->nullable()->unique()->after('slug');
        });

        DB::table('digital_profiles')->whereNull('card_identifier')->orderBy('id')->each(function ($row) {
            DB::table('digital_profiles')->where('id', $row->id)->update(['card_identifier' => ProfileCodes::profileIdentifier()]);
        });
    }

    public function down(): void
    {
        Schema::table('digital_profiles', function (Blueprint $table) {
            $table->dropUnique(['card_identifier']);
            $table->dropColumn('card_identifier');
        });
    }
};
