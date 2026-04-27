<?php

namespace Tests\Feature\Api;

use App\Models\Router;
use App\Models\SystemSetting;
use App\Models\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_manages_telegram_bots_without_exposing_token(): void
    {
        $router = $this->createRouter();

        $response = $this->postJson('/api/v1/admin/telegram-bots', [
            'bot_name' => 'NOC Bot',
            'token' => '123456:super-secret-token',
            'router_id' => $router->id,
            'status' => 'active',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bot_name', 'NOC Bot')
            ->assertJsonMissing(['token' => '123456:super-secret-token']);

        $storedToken = DB::table('telegram_bots')->value('token');

        $this->assertNotSame('123456:super-secret-token', $storedToken);

        $this->getJson('/api/v1/admin/telegram-bots')
            ->assertOk()
            ->assertJsonMissing(['token' => '123456:super-secret-token']);
    }

    public function test_it_manages_telegram_groups_and_sends_test_message(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $bot = TelegramBot::query()->create([
            'bot_name' => 'Admin Bot',
            'token' => '123456:test-token',
            'status' => 'active',
        ]);

        $group = $this->postJson('/api/v1/admin/telegram-groups', [
            'telegram_bot_id' => $bot->id,
            'group_name' => 'Admin Group',
            'chat_id' => '-100123456789',
            'group_type' => 'admin',
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('data.group_name', 'Admin Group')
            ->json('data');

        $this->postJson("/api/v1/admin/telegram-groups/{$group['id']}/test", [
            'message' => 'Ping dari test.',
        ])
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bot123456:test-token/sendMessage')
            && $request['chat_id'] === '-100123456789'
            && $request['text'] === 'Ping dari test.');
    }

    public function test_it_saves_database_settings_securely_and_tests_connection(): void
    {
        $payload = [
            'host' => (string) config('database.connections.mysql.host'),
            'port' => (string) config('database.connections.mysql.port'),
            'database' => (string) config('database.connections.mysql.database'),
            'username' => (string) config('database.connections.mysql.username'),
            'password' => (string) config('database.connections.mysql.password'),
        ];

        $this->patchJson('/api/v1/admin/database-settings/current', $payload)
            ->assertOk()
            ->assertJsonPath('data.has_password', true)
            ->assertJsonMissing(['password' => $payload['password']]);

        $storedPassword = SystemSetting::query()
            ->where('setting_group', 'database')
            ->where('setting_key', 'password')
            ->firstOrFail();

        $this->assertTrue($storedPassword->is_encrypted);
        $this->assertNotSame($payload['password'], $storedPassword->setting_value);

        $this->postJson('/api/v1/admin/database-settings/test', $payload)
            ->assertOk()
            ->assertJsonPath('data.connected', true);
    }

    private function createRouter(): Router
    {
        return Router::query()->create([
            'router_code' => 'RTR-TG-001',
            'router_name' => 'Router Telegram',
            'router_role' => 'bng',
            'mgmt_ip' => '10.60.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'location_name' => 'POP Telegram',
            'is_active' => true,
        ]);
    }
}
