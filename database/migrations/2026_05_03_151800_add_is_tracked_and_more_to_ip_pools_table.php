<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: table now created in create_ip_pools_table migration
    }

    public function down(): void
    {
        // No-op
    }
};
