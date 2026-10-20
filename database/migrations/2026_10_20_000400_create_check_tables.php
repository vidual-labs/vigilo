<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // client_id NULL = install-wide override; otherwise a per-client one.
        Schema::create('check_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('check_key', 40);
            $table->boolean('enabled')->nullable();
            $table->json('thresholds')->nullable();
            $table->timestamps();
            $table->index(['check_key', 'client_id']);
        });

        Schema::create('url_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->char('url_hash', 40);
            // ad | monitored
            $table->string('origin', 20);
            $table->string('final_url', 2048)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('redirect_chain')->nullable();
            $table->unsignedSmallInteger('redirect_count')->default(0);
            $table->unsignedInteger('response_ms')->nullable();
            $table->timestamp('tls_expires_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'url_hash']);
        });

        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            // schedule | manual | cli
            $table->string('trigger', 20);
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('running');
            $table->string('batch_id', 64)->nullable();
            $table->boolean('send_digest')->default(false);
            $table->json('stats')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('check_key', 40);
            // Same problem = same key = same row, so a problem that lasts a
            // week is one finding, not seven alerts.
            $table->string('dedupe_key', 191)->unique();
            $table->string('platform', 20)->nullable();
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            // Rendered in the reader's language: translation key + params.
            $table->string('title_key', 120);
            $table->json('title_params')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            // When it last became open (first detection or reopen).
            $table->timestamp('opened_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            // NULL with resolved_at set = resolved automatically.
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->string('notified_severity', 20)->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->unsignedInteger('reopen_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'severity']);
            $table->index(['client_id', 'status']);
            $table->index(['check_key', 'status']);
        });

        Schema::create('finding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('created_at');
            $table->index(['finding_id', 'created_at']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 2048);
            $table->text('secret');
            $table->boolean('send_digest')->default(true);
            $table->boolean('send_critical')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('finding_events');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('runs');
        Schema::dropIfExists('url_probes');
        Schema::dropIfExists('check_settings');
    }
};
