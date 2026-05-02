<?php

namespace App\Services\Hotspot\Radius;

use App\Models\HotspotVoucherSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class RadiusCoaService
{
    private int $coaPort;

    private int $timeout;

    private string $radclientPath;

    private string $dictionaryPath;

    public function __construct()
    {
        $this->coaPort = (int) config('hotspot.radius.coa_port', 3799);
        $this->timeout = (int) config('hotspot.radius.timeout', 5);
        $this->radclientPath = config('hotspot.radius.radclient_path', '/usr/bin/radclient');
        $this->dictionaryPath = config('hotspot.radius.dictionary_path', '/usr/share/freeradius/dictionary');
    }

    /**
     * Send Disconnect-Request to NAS to disconnect a user session
     *
     * This method:
     * 1. Queries hotspot_sessions for active sessions of the username
     * 2. Gets unique NAS IPs from those sessions
     * 3. Sends CoA disconnect to each NAS
     *
     * @return array{success: bool, disconnected_sessions: int, failed: int, message: string}
     */
    public function disconnectUser(string $username): array
    {
        // Get all active sessions for this username
        $activeSessions = HotspotVoucherSession::query()
            ->where('username', $username)
            ->whereNull('stopped_at')
            ->get();

        if ($activeSessions->isEmpty()) {
            return [
                'success' => true,
                'disconnected_sessions' => 0,
                'failed' => 0,
                'message' => 'No active sessions found.',
            ];
        }

        // Get unique NAS IPs
        $nasIps = $activeSessions->pluck('nas_ip_address')
            ->filter()
            ->unique()
            ->values();

        if ($nasIps->isEmpty()) {
            return [
                'success' => true,
                'disconnected_sessions' => 0,
                'failed' => 0,
                'message' => 'No NAS IPs found for active sessions.',
            ];
        }

        $disconnected = 0;
        $failed = 0;
        $errors = [];

        foreach ($nasIps as $nasIp) {
            // Get shared secret for this NAS
            $secret = $this->getNasSecret($nasIp);

            $result = $this->sendCoa($nasIp, $username, $secret);

            if ($result['success']) {
                $disconnected++;
            } else {
                $failed++;
                $errors[] = $result['message'];
            }
        }

        return [
            'success' => $failed === 0,
            'disconnected_sessions' => $disconnected,
            'failed' => $failed,
            'message' => $failed > 0
                ? 'Some CoA requests failed: ' . implode('; ', $errors)
                : 'All CoA requests sent successfully.',
        ];
    }

    /**
     * Send CoA disconnect to a specific NAS IP
     */
    public function disconnectFromNas(string $nasIp, string $username, ?string $secret = null): array
    {
        $secret ??= $this->getNasSecret($nasIp);

        return $this->sendCoa($nasIp, $username, $secret);
    }

    /**
     * Send CoA request using radclient if available
     */
    private function sendCoa(string $nasIp, string $username, string $secret): array
    {
        // Check if radclient is available
        if (file_exists($this->radclientPath) && is_executable($this->radclientPath)) {
            return $this->sendCoaWithRadclient($nasIp, $username, $secret);
        }

        // Fallback to raw socket
        return $this->sendCoaWithSocket($nasIp, $username, $secret);
    }

    /**
     * Send CoA using radclient binary
     */
    private function sendCoaWithRadclient(string $nasIp, string $username, string $secret): array
    {
        // Build CoA-Request packet
        $coaRequest = "Acct-Session-Id=* User-Name={$username}";

        $result = Process::timeout($this->timeout)
            ->run("echo '" . $coaRequest . "' | {$this->radclientPath} -x {$nasIp}:{$this->coaPort} disconnect " . escapeshellarg($secret));

        if ($result->successful()) {
            return [
                'success' => true,
                'message' => 'CoA disconnect sent successfully via radclient.',
            ];
        }

        return [
            'success' => false,
            'message' => 'CoA disconnect failed: ' . trim($result->errorOutput()),
        ];
    }

    /**
     * Send CoA using raw PHP sockets (fallback)
     */
    private function sendCoaWithSocket(string $nasIp, string $username, string $secret): array
    {
        $socket = @fsockopen($nasIp, $this->coaPort, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            return [
                'success' => false,
                'message' => "Cannot connect to CoA port {$nasIp}:{$this->coaPort}: {$errstr} ({$errno})",
            ];
        }

        try {
            // Build CoA-Request packet
            $packet = $this->buildCoaPacket($username, $secret);

            $sent = @fwrite($socket, $packet);

            if ($sent === false) {
                fclose($socket);

                return [
                    'success' => false,
                    'message' => 'Failed to send CoA packet.',
                ];
            }

            // Wait for response (with timeout)
            stream_set_timeout($socket, $this->timeout);
            $response = @fread($socket, 1024);

            fclose($socket);

            return [
                'success' => true,
                'message' => 'CoA packet sent.' . ($response ? ' Response: ' . bin2hex($response) : ''),
            ];
        } catch (\Throwable $e) {
            @fclose($socket);

            return [
                'success' => false,
                'message' => 'CoA socket error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build CoA-Request packet
     */
    private function buildCoaPacket(string $username, string $secret): string
    {
        $code = chr(0xCA); // CoA-Request
        $id = chr(rand(1, 255));
        $length = chr(0x00) . chr(0x00); // Will be filled

        // Build attributes
        $attributes = '';
        $attributes .= $this->attributeUserName($username);
        $attributes .= $this->attributeTerminationCause();

        // Request authenticator
        $authenticator = Str::random(16);

        // Packet without length and signature
        $packetWithoutLength = $code . $id . $length . $authenticator . $attributes;

        // Calculate length
        $len = strlen($packetWithoutLength);
        $packetWithoutLength[2] = chr(($len >> 8) & 0xFF);
        $packetWithoutLength[3] = chr($len & 0xFF);

        // Add MD5 signature
        $signature = md5($code . $id . substr($packetWithoutLength, 2) . $secret . $authenticator . $attributes, true);

        return $packetWithoutLength . $signature;
    }

    private function attributeUserName(string $username): string
    {
        $value = $username;
        $attrType = chr(0x01); // User-Name

        return $attrType . chr(strlen($value) + 2) . $value;
    }

    private function attributeTerminationCause(): string
    {
        $value = chr(0x01) . chr(0x00) . chr(0x00) . chr(0x06); // Admin-Reset
        $attrType = chr(0x1E); // Termination-Type

        return $attrType . chr(strlen($value) + 2) . $value;
    }

    /**
     * Get shared secret for a NAS from database or config
     */
    private function getNasSecret(string $nasIp): string
    {
        $cacheKey = "nas_secret:{$nasIp}";

        return Cache::remember($cacheKey, 3600, function () use ($nasIp) {
            $nas = \App\Models\Nas::query()
                ->where('nasname', $nasIp)
                ->first();

            return $nas?->secret ?? config('hotspot.radius.default_nas_secret', 'secret');
        });
    }

    /**
     * Check if radclient is available
     */
    public function isRadclientAvailable(): bool
    {
        return file_exists($this->radclientPath) && is_executable($this->radclientPath);
    }
}
