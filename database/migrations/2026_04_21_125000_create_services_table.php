<?php

use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('package_id')->constrained();
            $table->foreignId('router_id')->constrained();
            $table->foreignId('olt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ont_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vid_id')->nullable()->constrained('vids')->nullOnDelete();
            $table->string('service_code', 50)->unique();
            $table->unsignedSmallInteger('monitor_vid');
            $table->string('monitor_pppoe_username', 100)->unique();
            $table->string('monitor_pppoe_password', 255);
            $table->unsignedSmallInteger('internet_vid');
            $table->string('subnet_cidr', 50);
            $table->string('gateway_ip', 45)->nullable();
            $table->string('dhcp_pool_start', 45);
            $table->string('dhcp_pool_end', 45);
            $table->unsignedSmallInteger('ip_pool_count')->default(5);
            $table->unsignedSmallInteger('rate_limit_mbps')->default(10);
            $table->string('isolation_method', 50)->default(ServiceIsolationMethod::AddressList->value);
            $table->string('address_list_name', 100)->nullable();
            $table->string('billing_status', 30)->default(ServiceBillingStatus::Pending->value);
            $table->string('network_status', 30)->default(ServiceNetworkStatus::Provisioning->value);
            $table->string('overall_status', 30)->default(ServiceOverallStatus::Provisioning->value);
            $table->date('activation_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('package_id');
            $table->index('router_id');
            $table->index('olt_id');
            $table->index('ont_id');
            $table->index('vid_id');
            $table->index('monitor_vid');
            $table->index('internet_vid');
            $table->index('billing_status');
            $table->index('network_status');
            $table->index('overall_status');
            $table->index('activation_date');
            $table->index('deleted_at');
            $table->index(['customer_id', 'overall_status']);
            $table->index(['router_id', 'internet_vid']);
            $table->index(['vid_id', 'overall_status']);
            $table->index(['ont_id', 'overall_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
