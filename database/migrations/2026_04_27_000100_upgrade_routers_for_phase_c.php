<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('routers')) {
            Schema::create('routers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('host');
                $table->unsignedInteger('api_port')->default(8728);
                $table->unsignedSmallInteger('timeout')->nullable();
                $table->boolean('use_ssl')->default(false);
                $table->string('status', 20)->default('active');
                $table->string('api_username')->nullable();
                $table->text('api_password')->nullable();
                $table->string('acs_inform_url', 2048)->nullable();
                $table->string('acs_nbi_url', 2048)->nullable();
                $table->string('acs_username')->nullable();
                $table->text('acs_password')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('routers', function (Blueprint $table): void {
            if (! Schema::hasColumn('routers', 'name')) {
                $table->string('name')->nullable();
            }

            if (! Schema::hasColumn('routers', 'host')) {
                $table->string('host')->nullable();
            }

            if (! Schema::hasColumn('routers', 'api_port')) {
                $table->unsignedInteger('api_port')->nullable();
            }

            if (! Schema::hasColumn('routers', 'timeout')) {
                $table->unsignedSmallInteger('timeout')->nullable();
            }

            if (! Schema::hasColumn('routers', 'use_ssl')) {
                $table->boolean('use_ssl')->default(false);
            }

            if (! Schema::hasColumn('routers', 'status')) {
                $table->string('status', 20)->nullable();
            }

            if (! Schema::hasColumn('routers', 'api_username')) {
                $table->string('api_username')->nullable();
            }

            if (! Schema::hasColumn('routers', 'api_password')) {
                $table->text('api_password')->nullable();
            }

            if (! Schema::hasColumn('routers', 'acs_inform_url')) {
                $table->string('acs_inform_url', 2048)->nullable();
            }

            if (! Schema::hasColumn('routers', 'acs_nbi_url')) {
                $table->string('acs_nbi_url', 2048)->nullable();
            }

            if (! Schema::hasColumn('routers', 'acs_username')) {
                $table->string('acs_username')->nullable();
            }

            if (! Schema::hasColumn('routers', 'acs_password')) {
                $table->text('acs_password')->nullable();
            }

            if (! Schema::hasColumn('routers', 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn('routers', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('routers', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        $this->makeCredentialColumnsNullable();
        $this->backfillPhaseCColumns();
    }

    public function down(): void
    {
        if (! Schema::hasTable('routers')) {
            return;
        }

        Schema::table('routers', function (Blueprint $table): void {
            $columns = [
                'name',
                'host',
                'timeout',
                'use_ssl',
                'status',
                'acs_inform_url',
                'acs_nbi_url',
                'acs_username',
                'acs_password',
                'description',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('routers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function makeCredentialColumnsNullable(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (Schema::hasColumn('routers', 'api_username')) {
            DB::statement('ALTER TABLE `routers` MODIFY `api_username` VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('routers', 'api_password')) {
            DB::statement('ALTER TABLE `routers` MODIFY `api_password` TEXT NULL');
        }
    }

    private function backfillPhaseCColumns(): void
    {
        $select = ['id', 'name', 'host', 'status', 'timeout', 'use_ssl'];

        foreach (['router_name', 'mgmt_ip', 'is_active'] as $legacyColumn) {
            if (Schema::hasColumn('routers', $legacyColumn)) {
                $select[] = $legacyColumn;
            }
        }

        DB::table('routers')
            ->select($select)
            ->orderBy('id')
            ->chunkById(200, function ($routers): void {
                foreach ($routers as $router) {
                    $name = $this->firstFilled($router->name ?? null, $router->router_name ?? null);
                    $host = $this->firstFilled($router->host ?? null, $router->mgmt_ip ?? null);

                    $status = $this->firstFilled($router->status ?? null);
                    if ($status === null && isset($router->is_active)) {
                        $status = (bool) $router->is_active ? 'active' : 'inactive';
                    }

                    if ($status === null) {
                        $status = 'active';
                    }

                    $updates = [];

                    if (($router->name ?? null) !== $name) {
                        $updates['name'] = $name;
                    }

                    if (($router->host ?? null) !== $host) {
                        $updates['host'] = $host;
                    }

                    if (($router->status ?? null) !== $status) {
                        $updates['status'] = $status;
                    }

                    if (! is_numeric($router->timeout) || (int) $router->timeout <= 0) {
                        $updates['timeout'] = 5;
                    }

                    if ($router->use_ssl === null) {
                        $updates['use_ssl'] = false;
                    }

                    if ($updates === []) {
                        continue;
                    }

                    $updates['updated_at'] = now();

                    DB::table('routers')
                        ->where('id', $router->id)
                        ->update($updates);
                }
            });
    }

    private function firstFilled(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
};
