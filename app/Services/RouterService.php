<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class RouterService
{
    /**
     * @return array{success:bool,message:string,data:array<string,mixed>}
     */
    public function testConnection(Router $router): array
    {
        $timeout = max(1, (int) ($router->timeout ?? 5));

        try {
            $socket = @fsockopen((string) $router->host, (int) $router->api_port, $errno, $errstr, $timeout);

            if ($socket === false) {
                return [
                    'success' => false,
                    'message' => sprintf('Koneksi router gagal: %s (%d).', $errstr !== '' ? $errstr : 'unknown error', (int) $errno),
                    'data' => [
                        'host' => $router->host,
                        'api_port' => (int) $router->api_port,
                        'timeout' => $timeout,
                    ],
                ];
            }

            fclose($socket);

            return [
                'success' => true,
                'message' => 'Koneksi router berhasil.',
                'data' => [
                    'host' => $router->host,
                    'api_port' => (int) $router->api_port,
                    'timeout' => $timeout,
                ],
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Koneksi router gagal diproses.',
                'data' => [
                    'host' => $router->host,
                    'api_port' => (int) $router->api_port,
                    'timeout' => $timeout,
                    'error' => $exception->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>}
     */
    public function testAcs(Router $router): array
    {
        $url = trim((string) ($router->acs_nbi_url ?? ''));

        if ($url === '') {
            return [
                'success' => false,
                'message' => 'ACS NBI URL belum diisi.',
                'data' => [],
            ];
        }

        try {
            $request = Http::timeout(5);

            $acsUsername = is_string($router->acs_username) ? trim($router->acs_username) : '';
            $acsPassword = is_string($router->acs_password) ? trim($router->acs_password) : '';

            if ($acsUsername !== '' && $acsPassword !== '') {
                $request = $request->withBasicAuth($acsUsername, $acsPassword);
            }

            $response = $request->get($url);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => sprintf('ACS merespons status HTTP %d.', $response->status()),
                    'data' => [
                        'url' => $url,
                        'status' => $response->status(),
                    ],
                ];
            }

            return [
                'success' => true,
                'message' => 'Koneksi ACS berhasil.',
                'data' => [
                    'url' => $url,
                    'status' => $response->status(),
                ],
            ];
        } catch (ConnectionException $exception) {
            return [
                'success' => false,
                'message' => 'ACS tidak dapat dihubungi. Pastikan GenieACS aktif.',
                'data' => [
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ],
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Uji koneksi ACS gagal diproses.',
                'data' => [
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{success:bool,message:string,data:array<int,mixed>}
     */
    public function syncOntPlaceholder(Router $router): array
    {
        return [
            'success' => true,
            'message' => 'Sync ONT placeholder. GenieACS integration belum aktif.',
            'data' => [],
        ];
    }
}
