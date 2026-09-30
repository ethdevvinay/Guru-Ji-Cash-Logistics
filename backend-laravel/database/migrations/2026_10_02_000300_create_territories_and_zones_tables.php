<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('territories');
            $table->string('type', 20);
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('territory_id')->constrained('territories');
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->geometry('boundary', subtype: 'polygon');
            $table->json('boundary_geojson');
            $table->string('color', 9)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();

            $table->spatialIndex('boundary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
        Schema::dropIfExists('territories');
    }
};
