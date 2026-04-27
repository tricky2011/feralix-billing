<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_router', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'router_id']);
            $table->index('router_id');
        });

        if (Schema::hasTable('user_router_assignments')) {
            DB::table('user_router_assignments')
                ->select(['user_id', 'router_id', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->get()
                ->each(function (object $assignment): void {
                    DB::table('user_router')->insertOrIgnore([
                        'user_id' => $assignment->user_id,
                        'router_id' => $assignment->router_id,
                        'created_at' => $assignment->created_at ?? now(),
                        'updated_at' => $assignment->updated_at ?? now(),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_router');
    }
};
