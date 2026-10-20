<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone', 64)->default('Europe/Berlin');
            $table->char('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            // Demo clients are created by the demo seeder and removed by
            // `vigilo:demo:remove`; real clients are never touched by either.
            $table->boolean('is_demo')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('client_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->decimal('monthly_amount', 12, 2);
            $table->timestamps();
            $table->unique(['client_id', 'platform']);
        });

        Schema::create('monitored_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_urls');
        Schema::dropIfExists('client_budgets');
        Schema::dropIfExists('clients');
    }
};
