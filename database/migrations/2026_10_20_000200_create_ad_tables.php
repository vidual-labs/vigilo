<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('name')->nullable();
            // Google: customer id, digits only. Meta: ad account id with act_ prefix.
            $table->string('account_id', 64);
            // Google manager (MCC) id sent as login-customer-id.
            $table->string('login_customer_id', 32)->nullable();
            // Optional per-connection token override (encrypted at rest).
            $table->text('credentials')->nullable();
            // Non-secret extras: mock scenario, account name/currency/status.
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('unknown');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error_code', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'platform', 'account_id']);
        });

        Schema::create('ad_metrics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->date('date');
            $table->string('campaign_id', 64);
            $table->string('campaign_name')->nullable();
            $table->decimal('spend', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('conversions', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['ad_connection_id', 'date', 'campaign_id']);
            $table->index(['client_id', 'date']);
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('external_id', 64);
            $table->string('name')->nullable();
            $table->string('campaign_id', 64)->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('ad_group_id', 64)->nullable();
            $table->string('ad_group_name')->nullable();
            // Ad, ad group/set and campaign all enabled.
            $table->boolean('is_active')->default(true);
            $table->string('delivery_status', 40)->nullable();
            // approved | limited | disapproved | under_review | unknown
            $table->string('review_status', 20)->default('unknown');
            $table->string('review_status_raw', 60)->nullable();
            $table->json('policy_topics')->nullable();
            $table->json('final_urls')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['ad_connection_id', 'external_id']);
            $table->index(['client_id', 'is_active']);
        });

        Schema::create('fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->morphs('subject');
            // metrics | ads | account | token | leads | probe | test
            $table->string('kind', 20);
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 32)->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->unsignedInteger('rows')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetch_logs');
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_metrics_daily');
        Schema::dropIfExists('ad_connections');
    }
};
