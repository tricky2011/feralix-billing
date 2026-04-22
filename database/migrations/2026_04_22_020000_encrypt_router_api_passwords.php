<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('routers') || ! Schema::hasColumn('routers', 'api_password')) {
            return;
        }

        DB::table('routers')
            ->select(['id', 'api_password'])
            ->orderBy('id')
            ->chunkById(100, function ($routers): void {
                foreach ($routers as $router) {
                    if (! is_string($router->api_password) || $router->api_password === '') {
                        continue;
                    }

                    try {
                        Crypt::decryptString($router->api_password);

                        continue;
                    } catch (DecryptException) {
                        // The stored value is still plaintext and needs to be encrypted.
                    }

                    DB::table('routers')
                        ->where('id', $router->id)
                        ->update([
                            'api_password' => Crypt::encryptString($router->api_password),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally left blank to avoid restoring plaintext credentials.
    }
};
