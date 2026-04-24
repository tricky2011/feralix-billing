<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('password')->index();
            }
        });

        $this->makeEmailNullable();
        $this->backfillUsernames();
        $this->ensureUsernameUniqueIndex();
        $this->makeUsernameRequired();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if ($this->indexExists('users_username_unique')) {
                $table->dropUnique('users_username_unique');
            }

            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            }
        });
    }

    private function backfillUsernames(): void
    {
        $used = [];

        DB::table('users')
            ->select(['id', 'name', 'email', 'username'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user) use (&$used): void {
                $currentUsername = trim((string) ($user->username ?? ''));
                $base = $currentUsername !== ''
                    ? $currentUsername
                    : $this->usernameBase((string) ($user->email ?? ''), (string) ($user->name ?? ''), (int) $user->id);

                $username = $this->uniqueUsername($base, (int) $user->id, $used);
                $used[$username] = true;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });
    }

    private function usernameBase(string $email, string $name, int $id): string
    {
        if ($email !== '' && str_contains($email, '@')) {
            return Str::before($email, '@');
        }

        if ($name !== '') {
            return $name;
        }

        return 'user'.$id;
    }

    private function uniqueUsername(string $base, int $id, array $used): string
    {
        $normalized = strtolower(trim($base));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '_', $normalized) ?: '';
        $normalized = trim($normalized, '._-');
        $normalized = $normalized !== '' ? $normalized : 'user'.$id;

        $candidate = $normalized;
        $suffix = 1;

        while (isset($used[$candidate]) || DB::table('users')->where('username', $candidate)->where('id', '!=', $id)->exists()) {
            $candidate = $normalized.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function makeEmailNullable(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL'),
            'pgsql' => DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL'),
            default => null,
        };
    }

    private function makeUsernameRequired(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('ALTER TABLE users MODIFY username VARCHAR(255) NOT NULL'),
            'pgsql' => DB::statement('ALTER TABLE users ALTER COLUMN username SET NOT NULL'),
            default => null,
        };
    }

    private function ensureUsernameUniqueIndex(): void
    {
        if ($this->indexExists('users_username_unique')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username');
        });
    }

    private function indexExists(string $indexName): bool
    {
        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'users')
                ->where('index_name', $indexName)
                ->exists(),
            default => collect(Schema::getIndexes('users'))
                ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName),
        };
    }
};
