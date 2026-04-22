<?php

namespace Tests\Unit\GenieAcs;

use App\Contracts\GenieAcs\GenieAcsClient;
use App\Models\Ont;
use App\Services\GenieAcs\GenieAcsOntSnapshotMapper;
use App\Services\GenieAcs\Providers\NbiGenieAcsOntDataProvider;
use Carbon\Carbon;
use Illuminate\Log\LogManager;
use Tests\TestCase;

class NbiGenieAcsOntDataProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-22 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_maps_device_payload_to_ont_snapshot_using_serial_lookup(): void
    {
        $client = new class implements GenieAcsClient
        {
            public array $calls = [];

            public function findDevices(array $query, array $projection = [], ?int $limit = null): array
            {
                $this->calls[] = [
                    'query' => $query,
                    'projection' => $projection,
                    'limit' => $limit,
                ];

                return [[
                    '_id' => 'genie-device-001',
                    '_lastInform' => '2026-04-22T09:57:00Z',
                    'DeviceID' => [
                        'SerialNumber' => [
                            '_value' => 'ONT-SN-001',
                        ],
                    ],
                    'VirtualParameters' => [
                        'Online' => [
                            '_value' => true,
                        ],
                        'OpticalStatus' => [
                            '_value' => 'normal',
                        ],
                        'OpticalInfo' => [
                            '_value' => 'RX -21.80 dBm | TX 2.10 dBm',
                        ],
                        'OpticalRxPower' => [
                            '_value' => '-21.80 dBm',
                        ],
                        'OpticalTxPower' => [
                            '_value' => '2.10 dBm',
                        ],
                        'WifiSSID' => [
                            '_value' => 'Feralix-Rumah',
                        ],
                        'WifiPassword' => [
                            '_value' => 'password-aman',
                        ],
                    ],
                ]];
            }
        };

        $provider = new NbiGenieAcsOntDataProvider(
            client: $client,
            mapper: $this->app->make(GenieAcsOntSnapshotMapper::class),
            logManager: $this->app->make(LogManager::class),
        );

        $snapshot = $provider->fetchOntSnapshot(new Ont([
            'ont_sn' => 'ONT-SN-001',
        ]));

        $this->assertNotNull($snapshot);
        $this->assertSame('genie-device-001', $snapshot->deviceId);
        $this->assertSame('ONT-SN-001', $snapshot->serialNumber);
        $this->assertSame('online', $snapshot->status);
        $this->assertSame('normal', $snapshot->opticalStatus);
        $this->assertSame('RX -21.80 dBm | TX 2.10 dBm', $snapshot->opticalInfo);
        $this->assertSame(-21.8, $snapshot->rxPower);
        $this->assertSame(2.1, $snapshot->txPower);
        $this->assertSame('Feralix-Rumah', $snapshot->ssidName);
        $this->assertSame('password-aman', $snapshot->wifiPassword);
        $this->assertSame('2026-04-22T09:57:00.000000Z', $snapshot->lastInformAt?->toISOString());
        $this->assertSame(1, count($client->calls));
        $this->assertSame(1, $client->calls[0]['limit']);
        $this->assertSame([
            '$or' => [
                ['DeviceID.SerialNumber' => 'ONT-SN-001'],
                ['Device.DeviceInfo.SerialNumber' => 'ONT-SN-001'],
                ['InternetGatewayDevice.DeviceInfo.SerialNumber' => 'ONT-SN-001'],
            ],
        ], $client->calls[0]['query']);
    }
}
