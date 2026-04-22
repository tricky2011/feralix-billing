<?php

use App\Enums\HotspotExpiredMode;
use App\Enums\HotspotProfileUserLock;
use App\Enums\HotspotProfileValidityMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('profile_name', 150)->unique();
            $table->string('validity_mode', 50)->default(HotspotProfileValidityMode::DaysAfterFirstLogin->value);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->unsignedBigInteger('data_limit_bytes')->nullable();
            $table->decimal('price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->string('user_lock', 20)->default(HotspotProfileUserLock::Mac->value);
            $table->string('expired_mode', 20)->default(HotspotExpiredMode::Time->value);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('validity_mode');
            $table->index('expired_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_profiles');
    }
};
