<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('occurred_at', 6);
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->string('actor_role', 20)->nullable();
            $table->string('actor_type', 20);
            $table->string('action', 64);
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 40)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('seal_seq')->nullable()->unique();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64)->nullable()->unique();
            $table->dateTime('sealed_at', 6)->nullable();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
