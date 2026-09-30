<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('device_id')->nullable()->after('tokenable_id')->constrained('devices');
            $table->string('last_used_ip', 45)->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn('last_used_ip');
        });
    }
};
