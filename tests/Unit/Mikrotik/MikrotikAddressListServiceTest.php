<?php

namespace Tests\Unit\Mikrotik;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikAddressListService;
use Illuminate\Log\LogManager;
use Tests\TestCase;

class MikrotikAddressListServiceTest extends TestCase
{
    public function test_it_adds_an_address_once_and_skips_duplicates(): void
    {
        $router = $this->makeRouter();
        $client = new AddressListFakeMikrotikApiClient();
        $service = new MikrotikAddressListService(
            new AddressListFakeMikrotikApiClientFactory($client),
            $this->app->make(LogManager::class),
        );

        $first = $service->ensureAddressListed($router, 'ISOLIR_CUSTOMER', '10.20.30.2', 'test comment');
        $second = $service->ensureAddressListed($router, 'ISOLIR_CUSTOMER', '10.20.30.2', 'test comment');

        $this->assertSame('added', $first['action']);
        $this->assertSame('already_present', $second['action']);
        $this->assertCount(1, $client->addressListEntries);
    }

    public function test_it_removes_existing_entries_and_ignores_missing_ones(): void
    {
        $router = $this->makeRouter();
        $client = new AddressListFakeMikrotikApiClient();
        $service = new MikrotikAddressListService(
            new AddressListFakeMikrotikApiClientFactory($client),
            $this->app->make(LogManager::class),
        );

        $service->ensureAddressListed($router, 'ISOLIR_CUSTOMER', '10.20.30.2', 'test comment');

        $removed = $service->ensureAddressRemoved($router, 'ISOLIR_CUSTOMER', '10.20.30.2');
        $missing = $service->ensureAddressRemoved($router, 'ISOLIR_CUSTOMER', '10.20.30.2');

        $this->assertSame('removed', $removed['action']);
        $this->assertSame('already_absent', $missing['action']);
        $this->assertCount(0, $client->addressListEntries);
    }

    public function test_it_removes_legacy_format_entries_by_comment_pattern(): void
    {
        $router = $this->makeRouter();
        $client = new AddressListFakeMikrotikApiClient();
        $service = new MikrotikAddressListService(
            new AddressListFakeMikrotikApiClientFactory($client),
            $this->app->make(LogManager::class),
        );

        // Simulate an entry created by the old isolation flow with a different address
        // that won't match the current target_address.
        $client->addressListEntries[] = [
            '.id' => '*legacy1',
            'list' => 'ISOLIR_CUSTOMER',
            'address' => '10.20.30.0/29',
            'comment' => 'feralix-billing isolate service:SVC-001 isolation:42',
        ];

        // New-format entry for the same customer using the current target_address.
        $service->ensureAddressListed($router, 'ISOLIR_CUSTOMER', '10.20.30.2', 'CUST-001 | VID-310');

        $this->assertCount(2, $client->addressListEntries);

        // Release using new target_address + legacy comment patterns.
        $result = $service->ensureAddressRemoved(
            $router,
            'ISOLIR_CUSTOMER',
            '10.20.30.2',
            ['SVC-001', 'service:SVC-001', 'isolation:42'],
        );

        $this->assertSame('removed', $result['action']);
        $this->assertSame(2, $result['matched']);
        $this->assertCount(0, $client->addressListEntries);
    }

    public function test_it_does_not_remove_entries_belonging_to_other_customers(): void
    {
        $router = $this->makeRouter();
        $client = new AddressListFakeMikrotikApiClient();
        $service = new MikrotikAddressListService(
            new AddressListFakeMikrotikApiClientFactory($client),
            $this->app->make(LogManager::class),
        );

        // Entry for a DIFFERENT customer – should not be touched.
        $client->addressListEntries[] = [
            '.id' => '*other1',
            'list' => 'ISOLIR_CUSTOMER',
            'address' => '10.20.40.0/29',
            'comment' => 'feralix-billing isolate service:SVC-999 isolation:99',
        ];

        // Entry for the target customer.
        $client->addressListEntries[] = [
            '.id' => '*mine1',
            'list' => 'ISOLIR_CUSTOMER',
            'address' => '10.20.30.2',
            'comment' => 'feralix-billing isolate service:SVC-001 isolation:42',
        ];

        $result = $service->ensureAddressRemoved(
            $router,
            'ISOLIR_CUSTOMER',
            '10.20.30.2',
            ['SVC-001', 'service:SVC-001', 'isolation:42'],
        );

        $this->assertSame('removed', $result['action']);
        $this->assertSame(1, $result['matched']);
        $this->assertCount(1, $client->addressListEntries);
        $this->assertSame('*other1', $client->addressListEntries[0]['.id']);
    }

    private function makeRouter(): Router
    {
        $router = new Router([
            'router_code' => 'RTR-ADDR-1',
            'router_name' => 'Router AddressList 1',
            'router_role' => 'bng',
            'mgmt_ip' => '10.10.10.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_active' => true,
        ]);
        $router->setAttribute('id', 1);

        return $router;
    }
}

class AddressListFakeMikrotikApiClientFactory implements MikrotikApiClientFactory
{
    public function __construct(private readonly AddressListFakeMikrotikApiClient $client) {}

    public function forRouter(Router $router): MikrotikApiClient
    {
        return $this->client;
    }
}

class AddressListFakeMikrotikApiClient implements MikrotikApiClient
{
    /** @var list<array<string, string>> */
    public array $addressListEntries = [];

    private int $sequence = 0;

    public function print(string $menuPath, array $properties = [], array $where = []): array
    {
        if ('/ip/firewall/address-list' !== '/'.trim($menuPath, '/')) {
            return [];
        }

        return array_values(array_filter(
            $this->addressListEntries,
            static function (array $entry) use ($where): bool {
                foreach ($where as $key => $value) {
                    if (! isset($entry[$key]) || $entry[$key] !== (string) $value) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    public function add(string $menuPath, array $attributes = []): void
    {
        $this->sequence++;
        $this->addressListEntries[] = [
            '.id' => '*'.$this->sequence,
            'list' => (string) ($attributes['list'] ?? ''),
            'address' => (string) ($attributes['address'] ?? ''),
            'comment' => (string) ($attributes['comment'] ?? ''),
        ];
    }

    public function remove(string $menuPath, string $id): void
    {
        $this->addressListEntries = array_values(array_filter(
            $this->addressListEntries,
            static fn (array $entry): bool => ($entry['.id'] ?? null) !== $id
        ));
    }

    public function set(string $menuPath, string $id, array $attributes): void
    {
        // No-op for fake client.
    }

    public function setWhere(string $menuPath, array $where, array $attributes): void
    {
        // No-op for fake client.
    }

    public function removeWhere(string $menuPath, array $where): void
    {
        // No-op for fake client.
    }

    public function command(string $menuPath, array $attributes = []): void
    {
        // No-op for fake client.
    }

    public function disconnect(): void
    {
        // No-op for fake client.
    }
}
