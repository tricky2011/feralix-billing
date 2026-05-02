<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nas', function (Blueprint $table): void {
            $table->id();
            // nasname: IP atau hostname router MikroTik
            $table->string('nasname', 255)->unique();
            // shortname: nama display router (misal: RB-UNGARAN-01)
            $table->string('shortname', 64)->nullable();
            // type: jenis NAS (default: mikrotik)
            $table->string('type', 30)->default('other');
            // ports: port RADIUS (default: 1812, 1813)
            $table->integer('ports')->nullable();
            // secret: shared secret untuk RADIUS authentication
            $table->string('secret', 60)->default('');
            // server: server RADIUS (kosong = server ini)
            $table->string('server', 64)->nullable();
            // community: SNMP community string (optional)
            $table->string('community', 50)->nullable();
            // description: keterangan tambahan
            $table->string('description', 200)->nullable();
            $table->timestamps();

            $table->index('nasname', 'idx_nas_nasname');
            $table->index('shortname', 'idx_nas_shortname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nas');
    }
};
