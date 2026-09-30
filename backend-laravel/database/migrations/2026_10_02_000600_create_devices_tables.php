<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('platform', 10)->default('android');
            $table->char('fingerprint_hash', 64);
            $table->text('public_key_pem');
            $table->boolean('key_hardware_backed')->nullable();
            $table->boolean('attestation_ok')->nullable();
            $table->json('last_integrity_verdict')->nullable();
            $table->string('model', 80)->nullable();
            $table->string('manufacturer', 80)->nullable();
            $table->string('os_version', 20)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('status', 20);
            $table->tinyInteger('active_marker')->nullable()->storedAs("CASE WHEN status = 'ACTIVE' THEN 1 ELSE NULL END");
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users');
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoke_reason', 120)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'active_marker']);
            $table->unique(['fingerprint_hash', 'active_marker']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('request_nonces', function (Blueprint $table): void {
            $table->foreignId('device_id')->constrained('devices');
            $table->string('nonce', 64);
            $table->dateTime('expires_at');

            $table->primary(['device_id', 'nonce']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_nonces');
        Schema::dropIfExists('devices');
    }
};
