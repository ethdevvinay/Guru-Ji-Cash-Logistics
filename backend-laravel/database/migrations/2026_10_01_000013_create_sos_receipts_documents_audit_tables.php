<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('collectors')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedTinyInteger('battery_percent')->default(100);
            $table->unsignedBigInteger('cash_holding_paise')->default(0);
            $table->foreignId('active_pickup_id')->nullable()->constrained('pickup_requests')->nullOnDelete();
            $table->enum('status', ['TRIGGERED', 'ACKNOWLEDGED', 'RESOLVED'])->default('TRIGGERED')->index();
            $table->foreignId('acknowledged_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolved_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 64)->unique()->index();
            $table->foreignId('cash_collection_id')->constrained('cash_collections')->cascadeOnDelete();
            $table->string('qr_verification_token', 128)->unique();
            $table->mediumText('thermal_payload_escpos')->nullable();
            $table->string('pdf_storage_path', 255)->nullable();
            $table->timestamp('whatsapp_delivered_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('document_type', ['GST', 'KYC_AADHAR', 'KYC_PAN', 'SHOP_PHOTO', 'COLLECTOR_ID', 'BIKE_RC'])->index();
            $table->string('file_path', 255);
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('file_size_bytes');
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 50);
            $table->string('action', 100)->index();
            $table->string('entity_type', 100)->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_id', 100)->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('sos_alerts');
    }
};
