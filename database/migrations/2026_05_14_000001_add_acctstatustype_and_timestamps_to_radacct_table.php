<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radacct', function (Blueprint $table): void {
            $table->string('acctstatustype', 32)->charset('utf8')->nullable()->after('acctauthentic');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('radacct', function (Blueprint $table): void {
            $table->dropColumn(['acctstatustype', 'created_at', 'updated_at']);
        });
    }
};
