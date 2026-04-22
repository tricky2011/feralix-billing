<?php

use App\Enums\ResellerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resellers', function (Blueprint $table): void {
            $table->id();
            $table->string('reseller_code', 50)->unique();
            $table->string('full_name', 150);
            $table->string('phone', 30)->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status', 20)->default(ResellerStatus::Active->value);
            $table->timestamps();

            $table->index('status');
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resellers');
    }
};
