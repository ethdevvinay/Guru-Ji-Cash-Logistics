<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retailers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('retailer_code', 20)->unique();
            $table->string('shop_name', 160);
            $table->string('owner_name', 120);
            $table->string('mobile', 16)->unique();
            $table->string('alt_mobile', 16)->nullable();
            $table->string('address', 255);
            $table->string('landmark', 160)->nullable();
            $table->string('city', 80);
            $table->string('pincode', 6);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->foreignId('territory_id')->nullable()->constrained('territories');
            $table->foreignId('default_collector_id')->nullable()->constrained('collectors');
            $table->string('gstin', 15)->nullable()->unique();
            $table->text('pan_encrypted')->nullable();
            $table->string('kyc_status', 20)->default('PENDING');
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedInteger('penalty_amount_paise')->nullable();
            $table->unsignedInteger('penalty_collector_share_paise')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['zone_id', 'status']);
        });

        DB::statement('ALTER TABLE retailers ADD CONSTRAINT chk_retailers_penalty_amount CHECK (penalty_amount_paise IS NULL OR penalty_amount_paise BETWEEN 5000 AND 20000)');
        DB::statement('ALTER TABLE retailers ADD CONSTRAINT chk_retailers_penalty_share CHECK (penalty_collector_share_paise IS NULL OR penalty_collector_share_paise <= COALESCE(penalty_amount_paise, 20000))');

        Schema::create('retailer_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers');
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('shop_role', 10);
            $table->boolean('can_request')->default(true);
            $table->boolean('can_confirm')->default(true);
            $table->boolean('can_spend')->default(false);
            $table->boolean('can_manage_staff')->default(false);
            $table->string('status', 20)->default('ACTIVE');
            $table->tinyInteger('owner_marker')->nullable()->storedAs("CASE WHEN shop_role = 'OWNER' AND status = 'ACTIVE' THEN 1 ELSE NULL END");
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['retailer_id', 'owner_marker']);
        });

        Schema::create('retailer_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retailer_id')->constrained('retailers');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('source', 20);
            $table->string('reason', 255)->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users');
            $table->dateTime('effective_from', 6);
            $table->timestamp('created_at')->nullable();

            $table->index(['retailer_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailer_locations');
        Schema::dropIfExists('retailer_users');
        Schema::dropIfExists('retailers');
    }
};
