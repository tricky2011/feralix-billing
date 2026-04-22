<?php

use App\Enums\HotspotVoucherSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_voucher_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotspot_voucher_id')->constrained()->cascadeOnDelete();
            $table->string('acct_session_id', 120);
            $table->string('session_key', 191)->unique();
            $table->string('nas_identifier', 120)->nullable();
            $table->string('nas_ip_address', 45)->nullable();
            $table->string('called_station_id', 120)->nullable();
            $table->string('calling_station_id', 32)->nullable();
            $table->string('framed_ip_address', 45)->nullable();
            $table->string('status', 20)->default(HotspotVoucherSessionStatus::Open->value);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('last_interim_at')->nullable();
            $table->dateTime('stopped_at')->nullable();
            $table->unsignedInteger('session_time_seconds')->default(0);
            $table->unsignedBigInteger('input_octets')->default(0);
            $table->unsignedBigInteger('output_octets')->default(0);
            $table->unsignedBigInteger('total_octets')->default(0);
            $table->string('terminate_cause', 60)->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamps();

            $table->index('acct_session_id');
            $table->index('status');
            $table->index('started_at');
            $table->index(['hotspot_voucher_id', 'status']);
            $table->index(['nas_identifier', 'acct_session_id']);
        });

        Schema::create('hotspot_radius_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotspot_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotspot_voucher_session_id')
                ->nullable()
                ->constrained('hotspot_voucher_sessions')
                ->nullOnDelete();
            $table->string('event_type', 40);
            $table->string('outcome', 20)->default('processed');
            $table->string('username', 100)->nullable();
            $table->string('acct_session_id', 120)->nullable();
            $table->string('nas_identifier', 120)->nullable();
            $table->string('nas_ip_address', 45)->nullable();
            $table->string('called_station_id', 120)->nullable();
            $table->string('calling_station_id', 32)->nullable();
            $table->string('framed_ip_address', 45)->nullable();
            $table->unsignedInteger('session_time_seconds')->nullable();
            $table->unsignedBigInteger('input_octets')->nullable();
            $table->unsignedBigInteger('output_octets')->nullable();
            $table->unsignedBigInteger('total_octets')->nullable();
            $table->string('reason_code', 40)->nullable();
            $table->string('message', 255)->nullable();
            $table->dateTime('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('occurred_at');
            $table->index('reason_code');
            $table->index('username');
            $table->index(['hotspot_voucher_id', 'occurred_at']);
            $table->index(['hotspot_voucher_session_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_radius_events');
        Schema::dropIfExists('hotspot_voucher_sessions');
    }
};
