<?php

use App\Enums\ServiceAccessMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('access_mode', 20)
                ->default(ServiceAccessMode::Vlan->value)
                ->after('rate_limit_mbps');
            $table->string('pppoe_username', 100)->nullable()->after('access_mode');
            $table->string('pppoe_password', 255)->nullable()->after('pppoe_username');
            $table->string('pppoe_isolation_profile', 100)->nullable()->after('pppoe_password');
            $table->string('static_ip_address', 45)->nullable()->after('pppoe_isolation_profile');
            $table->string('static_mac_address', 50)->nullable()->after('static_ip_address');
            $table->string('static_queue_name', 100)->nullable()->after('static_mac_address');
            $table->timestamp('legacy_secret_migrated_at')->nullable()->after('static_queue_name');

            $table->index('access_mode');
            $table->index('pppoe_username');
            $table->index('static_ip_address');
            $table->index('legacy_secret_migrated_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['access_mode']);
            $table->dropIndex(['pppoe_username']);
            $table->dropIndex(['static_ip_address']);
            $table->dropIndex(['legacy_secret_migrated_at']);

            $table->dropColumn([
                'access_mode',
                'pppoe_username',
                'pppoe_password',
                'pppoe_isolation_profile',
                'static_ip_address',
                'static_mac_address',
                'static_queue_name',
                'legacy_secret_migrated_at',
            ]);
        });
    }
};
