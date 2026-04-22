<?php

use App\Enums\ServiceIsolationTargetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_isolations', function (Blueprint $table): void {
            $table->string('target_type', 20)
                ->default(ServiceIsolationTargetType::Subnet->value)
                ->after('isolation_type');
            $table->string('target_identifier', 100)->nullable()->after('target_subnet');
            $table->json('target_payload')->nullable()->after('target_identifier');
        });

        DB::table('service_isolations')->update([
            'target_type' => ServiceIsolationTargetType::Subnet->value,
            'target_identifier' => DB::raw('target_subnet'),
        ]);

        Schema::table('service_isolations', function (Blueprint $table): void {
            $table->index('target_type');
            $table->index('target_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('service_isolations', function (Blueprint $table): void {
            $table->dropIndex(['target_type']);
            $table->dropIndex(['target_identifier']);
            $table->dropColumn([
                'target_type',
                'target_identifier',
                'target_payload',
            ]);
        });
    }
};
