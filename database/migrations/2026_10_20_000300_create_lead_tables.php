<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Lead METADATA only. There is deliberately no column that could hold a
 * name, email address, phone number or free-text answer — see
 * App\Domain\Leads\Data\LeadMetadata for the allow-list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            // lodgely: the client key from the contract.
            $table->string('external_key')->nullable();
            // Non-secret extras (mock scenario).
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('cursor', 512)->nullable();
            $table->string('status', 20)->default('unknown');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error_code', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 128);
            $table->timestamp('submitted_at');
            $table->string('status', 20)->default('unknown');
            $table->timestamp('status_changed_at')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->string('meta_campaign_id', 64)->nullable();
            $table->string('meta_adset_id', 64)->nullable();
            $table->string('meta_ad_id', 64)->nullable();
            $table->string('meta_form_id', 64)->nullable();
            $table->boolean('has_gclid')->default(false);
            $table->json('flags')->nullable();
            $table->timestamps();
            $table->unique(['lead_source_id', 'external_id']);
            $table->index(['client_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_sources');
    }
};
