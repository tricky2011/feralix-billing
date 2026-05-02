<?php

namespace App\Services\Genieacs;

use App\Models\Ont;
use Illuminate\Http\Client\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenieAcsWriteService
{
    private string $baseUrl;

    private ?string $username;

    private ?string $password;

    public function __construct()
    {
        $this->baseUrl = config('genieacs.nbi_url', 'http://localhost:7557');
        $this->username = config('genieacs.nbi_username');
        $this->password = config('genieacs.nbi_password');
    }

    /**
     * Set WiFi SSID
     *
     * @return array{success: bool, message: string}
     */
    public function setSSID(string $deviceId, string $ssid2g, ?string $ssid5g = null): array
    {
        $params = [];

        // SSID 2.4GHz
        $ssid2gPath = config('genieacs.params.ssid_2g');
        if ($ssid2gPath) {
            $params[$ssid2gPath] = $ssid2g;
        }

        // SSID 5GHz
        if ($ssid5g) {
            $ssid5gPath = config('genieacs.params.ssid_5g');
            if ($ssid5gPath) {
                $params[$ssid5gPath] = $ssid5g;
            }
        }

        if (empty($params)) {
            return [
                'success' => false,
                'message' => 'No SSID parameter paths configured.',
            ];
        }

        return $this->setParameterValues($deviceId, $params);
    }

    /**
     * Set WiFi password
     *
     * @return array{success: bool, message: string}
     */
    public function setWifiPassword(string $deviceId, string $password2g, ?string $password5g = null): array
    {
        $params = [];

        // Password 2.4GHz
        $pass2gPath = config('genieacs.params.pass_2g');
        if ($pass2gPath) {
            $params[$pass2gPath] = $password2g;
        }

        // Password 5GHz
        if ($password5g) {
            $pass5gPath = config('genieacs.params.pass_5g');
            if ($pass5gPath) {
                $params[$pass5gPath] = $password5g;
            }
        }

        if (empty($params)) {
            return [
                'success' => false,
                'message' => 'No password parameter paths configured.',
            ];
        }

        return $this->setParameterValues($deviceId, $params);
    }

    /**
     * Reboot ONT device
     *
     * @return array{success: bool, message: string}
     */
    public function rebootOnt(string $deviceId): array
    {
        return $this->sendTask($deviceId, ['name' => 'reboot']);
    }

    /**
     * Factory reset ONT device
     *
     * @return array{success: bool, message: string}
     */
    public function factoryResetOnt(string $deviceId): array
    {
        return $this->sendTask($deviceId, ['name' => 'factoryReset']);
    }

    /**
     * Get device by PPPoE username
     *
     * @return array{success: bool, device: array|null, message: string}
     */
    public function getDeviceByPppoeUsername(string $username): array
    {
        try {
            $pppPath = config('genieacs.params.ppp_username_path', 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username');

            $response = Http::timeout(10)
                ->withBasicAuth($this->username, $this->password)
                ->get("{$this->baseUrl}/devices", [
                    'query' => json_encode([
                        $pppPath . '._value' => $username,
                    ]),
                    'projection' => '_id,_deviceId',
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'device' => null,
                    'message' => 'GenieACS query failed: ' . $response->status(),
                ];
            }

            $devices = $response->json();

            if (empty($devices) || ! is_array($devices)) {
                return [
                    'success' => true,
                    'device' => null,
                    'message' => 'Device not found.',
                ];
            }

            return [
                'success' => true,
                'device' => $devices[0] ?? null,
                'message' => 'Device found.',
            ];
        } catch (\Throwable $e) {
            Log::error('GenieACS getDeviceByPppoeUsername failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'device' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get device info
     *
     * @return array{success: bool, device: array|null, message: string}
     */
    public function getDevice(string $deviceId): array
    {
        try {
            $response = Http::timeout(10)
                ->withBasicAuth($this->username, $this->password)
                ->get("{$this->baseUrl}/devices/{$deviceId}");

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'device' => null,
                    'message' => 'GenieACS get device failed: ' . $response->status(),
                ];
            }

            return [
                'success' => true,
                'device' => $response->json(),
                'message' => 'Device retrieved.',
            ];
        } catch (\Throwable $e) {
            Log::error('GenieACS getDevice failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'device' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Set parameter values on device
     *
     * @param array<string, string> $params Key-value pairs of parameter paths and values
     * @return array{success: bool, message: string}
     */
    private function setParameterValues(string $deviceId, array $params): array
    {
        try {
            $payload = [
                'parameterValues' => [],
            ];

            foreach ($params as $path => $value) {
                $payload['parameterValues'][] = [
                    'name' => $path,
                    'value' => $value,
                ];
            }

            $response = Http::timeout(30)
                ->withBasicAuth($this->username, $this->password)
                ->post("{$this->baseUrl}/devices/{$deviceId}/tasks?connection_request", $payload);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'GenieACS setParameterValues failed: ' . $response->status(),
                ];
            }

            Log::info('GenieACS setParameterValues sent', [
                'device_id' => $deviceId,
                'params' => $params,
            ]);

            return [
                'success' => true,
                'message' => 'Parameters queued for update.',
            ];
        } catch (\Throwable $e) {
            Log::error('GenieACS setParameterValues failed', [
                'device_id' => $deviceId,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send task to device
     *
     * @param array<string, mixed> $task Task payload
     * @return array{success: bool, message: string}
     */
    private function sendTask(string $deviceId, array $task): array
    {
        try {
            $response = Http::timeout(30)
                ->withBasicAuth($this->username, $this->password)
                ->post("{$this->baseUrl}/devices/{$deviceId}/tasks?connection_request", $task);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'GenieACS task failed: ' . $response->status(),
                ];
            }

            Log::info('GenieACS task sent', [
                'device_id' => $deviceId,
                'task' => $task,
            ]);

            return [
                'success' => true,
                'message' => 'Task queued for execution.',
            ];
        } catch (\Throwable $e) {
            Log::error('GenieACS task failed', [
                'device_id' => $deviceId,
                'task' => $task,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
