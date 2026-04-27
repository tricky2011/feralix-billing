<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('network_locations')) {
            Schema::create('network_locations', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code', 100)->nullable()->unique();
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20);
                $table->timestamps();

                $table->index('status');
                $table->index('name');
            });
        }

        if (Schema::hasTable('olts')) {
            if (! Schema::hasColumn('olts', 'network_location_id')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->foreignId('network_location_id')
                        ->nullable()
                        ->after('location_id')
                        ->constrained('network_locations')
                        ->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('olts', 'code')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->string('code', 100)->nullable()->after('olt_code');
                    $table->unique('code');
                });
            }

            if (! Schema::hasColumn('olts', 'name')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->string('name')->nullable()->after('olt_name');
                });
            }

            if (! Schema::hasColumn('olts', 'host')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->string('host')->nullable()->after('mgmt_ip');
                });
            }

            if (! Schema::hasColumn('olts', 'brand')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->string('brand')->nullable()->after('vendor_name');
                });
            }

            if (! Schema::hasColumn('olts', 'model')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->string('model')->nullable()->after('brand');
                });
            }

            if (! Schema::hasColumn('olts', 'pon_ports')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->unsignedInteger('pon_ports')->nullable()->after('model');
                });
            }

            if (! Schema::hasColumn('olts', 'status')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->string('status', 20)->nullable()->after('pon_ports');
                    $table->index('status');
                });
            }

            if (! Schema::hasColumn('olts', 'description')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->text('description')->nullable()->after('status');
                });
            }

            DB::table('olts')
                ->whereNull('code')
                ->whereNotNull('olt_code')
                ->update(['code' => DB::raw('olt_code')]);

            DB::table('olts')
                ->whereNull('name')
                ->update(['name' => DB::raw("COALESCE(olt_name, CONCAT('OLT-', id))")]);

            DB::table('olts')
                ->whereNull('host')
                ->whereNotNull('mgmt_ip')
                ->update(['host' => DB::raw('mgmt_ip')]);

            DB::table('olts')
                ->whereNull('brand')
                ->whereNotNull('vendor_name')
                ->update(['brand' => DB::raw('vendor_name')]);

            DB::table('olts')
                ->whereNull('status')
                ->update(['status' => DB::raw("CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END")]);
        }

        if (! Schema::hasTable('odcs')) {
            Schema::create('odcs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('location_id')->nullable()->constrained('network_locations')->nullOnDelete();
                $table->string('name');
                $table->string('code', 100)->nullable()->unique();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->unsignedInteger('used_ports')->default(0);
                $table->string('status', 20);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('name');
            });
        }

        if (! Schema::hasTable('odps')) {
            Schema::create('odps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('odc_id')->nullable()->constrained('odcs')->nullOnDelete();
                $table->foreignId('olt_id')->nullable()->constrained('olts')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('network_locations')->nullOnDelete();
                $table->string('name');
                $table->string('code', 100)->nullable()->unique();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->unsignedInteger('used_ports')->default(0);
                $table->string('status', 20);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odps');
        Schema::dropIfExists('odcs');

        if (Schema::hasTable('olts')) {
            if (Schema::hasColumn('olts', 'network_location_id')) {
                Schema::table('olts', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('network_location_id');
                });
            }

            Schema::table('olts', function (Blueprint $table): void {
                if (Schema::hasColumn('olts', 'code')) {
                    $table->dropUnique('olts_code_unique');
                    $table->dropColumn('code');
                }

                if (Schema::hasColumn('olts', 'name')) {
                    $table->dropColumn('name');
                }

                if (Schema::hasColumn('olts', 'host')) {
                    $table->dropColumn('host');
                }

                if (Schema::hasColumn('olts', 'brand')) {
                    $table->dropColumn('brand');
                }

                if (Schema::hasColumn('olts', 'model')) {
                    $table->dropColumn('model');
                }

                if (Schema::hasColumn('olts', 'pon_ports')) {
                    $table->dropColumn('pon_ports');
                }

                if (Schema::hasColumn('olts', 'status')) {
                    $table->dropIndex('olts_status_index');
                    $table->dropColumn('status');
                }

                if (Schema::hasColumn('olts', 'description')) {
                    $table->dropColumn('description');
                }
            });
        }

        Schema::dropIfExists('network_locations');
    }
};
