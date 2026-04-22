<?php

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_code', 30)->unique();
            $table->string('full_name', 150);
            $table->string('phone', 30);
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('customer_type', 30)->default(CustomerType::Residential->value);
            $table->string('status', 20)->default(CustomerStatus::Active->value);
            $table->timestamps();

            $table->index('customer_type');
            $table->index('status');
            $table->index(['full_name', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
