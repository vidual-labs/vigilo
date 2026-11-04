<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google attribution per lead, as numeric ids only (contract v1, optional
 * fields): campaign, ad group and keyword criterion from the ValueTrack final
 * URL suffix, carried by lodgely. Never a click id or keyword text. Basis for
 * keyword-level lead quality (VG-9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('google_campaign_id', 32)->nullable()->after('has_gclid');
            $table->string('google_ad_group_id', 32)->nullable()->after('google_campaign_id');
            $table->string('google_criterion_id', 32)->nullable()->after('google_ad_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['google_campaign_id', 'google_ad_group_id', 'google_criterion_id']);
        });
    }
};
