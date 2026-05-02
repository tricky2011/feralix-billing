<?php

namespace App\Services\Mikrotik\Clients;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Data\Mikrotik\MikrotikApiConnectionConfig;
use App\Exceptions\Mikrotik\MikrotikAuthenticationException;
use App\Exceptions\Mikrotik\MikrotikCommandException;
use App\Exceptions\Mikrotik\MikrotikConnectionException;
use App\Exceptions\Mikrotik\MikrotikQueryException;
use Illuminate\Log\LogManager;
use Throwable;

class SocketMikrotikApiClient implements MikrotikApiClient
{
    /** @var resource|null */
    private $socket = null;

    private bool $authenticated = false;

    public function __construct(
        private readonly MikrotikApiConnectionConfig $config,
        private readonly LogManager $logManager,
    ) {}

    public function __destruct()
    {
        $this->disconnect();
    }

    public function print(string $menuPath, array $properties = [], array $where = []): array
    {
        $normalizedPath = '/'.trim($menuPath, '/');
        $words = [rtrim($normalizedPath, '/').'/print'];

        if ($properties !== []) {
            $words[] = '=.proplist='.implode(',', $properties);
        }

        foreach ($this->sanitizeParams($where) as $property => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $words[] = sprintf('?%s=%s', $property, (string) $value);
        }

        $this->logger()->debug('Mikrotik print', [
            ...$this->config->logContext(),
            'path' => $menuPath,
            'params' => $this->sanitizeParamsForLog(['where' => $where, 'properties' => $properties]),
        ]);

        $replies = $this->talk($words);

        $records = [];

        foreach ($replies as $reply) {
            if ($reply['type'] !== '!re') {
                continue;
            }

            $records[] = $reply['attributes'];
        }

        return $records;
    }

    public function add(string $menuPath, array $attributes = []): void
    {
        $normalizedPath = '/'.trim($menuPath, '/');
        $words = [rtrim($normalizedPath, '/').'/add'];

        foreach ($this->sanitizeParams($attributes) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $words[] = sprintf('=%s=%s', $key, (string) $value);
        }

        $this->logger()->debug('Mikrotik add', [
            ...$this->config->logContext(),
            'path' => $menuPath,
            'params' => $this->sanitizeParamsForLog($attributes),
        ]);

        $this->talk($words);
    }

    public function remove(string $menuPath, string $id): void
    {
        $normalizedPath = '/'.trim($menuPath, '/');

        $this->logger()->debug('Mikrotik remove', [
            ...$this->config->logContext(),
            'path' => $menuPath,
            'id' => $id,
        ]);

        $this->talk([
            rtrim($normalizedPath, '/').'/remove',
            '=.id='.$id,
        ]);
    }

    public function set(string $menuPath, string $id, array $attributes): void
    {
        $normalizedPath = '/'.trim($menuPath, '/');
        $words = [rtrim($normalizedPath, '/').'/set'];

        $words[] = '=.id='.$id;

        foreach ($this->sanitizeParams($attributes) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $words[] = sprintf('=%s=%s', $key, (string) $value);
        }

        $this->logger()->debug('Mikrotik set', [
            ...$this->config->logContext(),
            'path' => $menuPath,
            'id' => $id,
            'params' => $this->sanitizeParamsForLog($attributes),
        ]);

        $this->talk($words);
    }

    public function setWhere(string $menuPath, array $where, array $attributes): void
    {
        $normalizedPath = '/'.trim($menuPath, '/');
        $words = [rtrim($normalizedPath, '/').'/set'];

        foreach ($this->sanitizeParams($where) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $words[] = sprintf('?%s=%s', $key, (string) $value);
        }

        foreach ($this->sanitizeParams($attributes) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $words[] = sprintf('=%s=%s', $key, (string) $value);
        }

        $this->logger()->debug('Mikrotik setWhere', [
            ...$this->config->logContext(),
            'path' => $menuPath,
            'where' => $where,
            'params' => $this->sanitizeParamsForLog($attributes),
        ]);

        $this->talk($words);
    }

    public function removeWhere(string $menuPath, array $where): void
    {
        $this->logger()->debug('Mikrotik removeWhere', [
            ...$this->config->logContext(),
            'path' => $menuPath,
            'where' => $where,
        ]);

        $items = $this->print($menuPath, ['.id'], $where);

        foreach ($items as $item) {
            if (isset($item['.id'])) {
                $this->remove($menuPath, $item['.id']);
            }
        }
    }

    public function command(string $menuPath, array $attributes = []): void
    {
        $normalizedPath = '/'.trim($menuPath, '/');
        $words = [$normalizedPath];

        foreach ($this->sanitizeParams($attributes) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $words[] = sprintf('=%s=%s', $key, (string) $value);
        }

        $this->logger()->debug('Mikrotik command', [
            ...$this->config->logContext(),
            'path' => $menuPath,
            'params' => $this->sanitizeParamsForLog($attributes),
        ]);

        $this->talk($words);
    }

    public function disconnect(): void
    {
        if (! is_resource($this->socket)) {
            return;
        }

        fclose($this->socket);
        $this->socket = null;
        $this->authenticated = false;
    }

    /**
     * @return list<array{type:string, attributes:array<string, string>}>
     */
    private function talk(array $words): array
    {
        $startedAt = microtime(true);

        try {
            $this->connectIfNeeded();
            $this->writeSentence($words);
            $replies = $this->readReplies();
        } catch (Throwable $throwable) {
            $this->logger()->error('Mikrotik API query failed.', [
                ...$this->config->logContext(),
                'command' => $words[0] ?? null,
                'message' => $throwable->getMessage(),
            ]);

            $this->disconnect();

            throw $throwable;
        }

        $errors = [];

        foreach ($replies as $reply) {
            if (! in_array($reply['type'], ['!trap', '!fatal'], true)) {
                continue;
            }

            $errors[] = $reply['attributes']['message'] ?? $reply['type'];
        }

        if ($errors !== []) {
            $errorMessage = implode(' | ', $errors);
            $this->logger()->error('Mikrotik query error', [
                ...$this->config->logContext(),
                'command' => $words[0] ?? null,
                'error' => $errorMessage,
            ]);
            throw new MikrotikCommandException($words[0] ?? $menuPath ?? 'unknown', $errorMessage);
        }

        $this->logger()->debug('Mikrotik API query completed.', [
            ...$this->config->logContext(),
            'command' => $words[0] ?? null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'records' => count(array_filter($replies, static fn (array $reply): bool => $reply['type'] === '!re')),
        ]);

        return $replies;
    }

    private function connectIfNeeded(): void
    {
        if (is_resource($this->socket) && $this->authenticated) {
            return;
        }

        $context = stream_context_create($this->config->streamContextOptions());
        $errorCode = 0;
        $errorMessage = '';

        $socket = @stream_socket_client(
            $this->config->endpoint(),
            $errorCode,
            $errorMessage,
            $this->config->connectTimeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! is_resource($socket)) {
            throw new MikrotikConnectionException(sprintf(
                'Unable to connect to %s:%d (%s).',
                $this->config->host,
                $this->config->port,
                $errorMessage !== '' ? $errorMessage : 'unknown error',
            ), $errorCode);
        }

        $seconds = (int) floor($this->config->readTimeout);
        $microseconds = (int) (($this->config->readTimeout - $seconds) * 1_000_000);

        stream_set_blocking($socket, true);
        stream_set_timeout($socket, $seconds, $microseconds);

        $this->socket = $socket;

        $this->authenticate();
    }

    private function authenticate(): void
    {
        $replies = $this->executeSentence([
            '/login',
            '=name='.$this->config->username,
            '=password='.$this->config->password,
        ]);

        foreach ($replies as $reply) {
            if (in_array($reply['type'], ['!trap', '!fatal'], true)) {
                throw new MikrotikAuthenticationException($reply['attributes']['message'] ?? 'Login failed.');
            }

            if ($reply['type'] === '!done' && isset($reply['attributes']['ret'])) {
                $this->authenticateLegacy($reply['attributes']['ret']);

                return;
            }
        }

        $this->authenticated = true;

        $this->logger()->info('Connected to Mikrotik API.', $this->config->logContext());
    }

    private function authenticateLegacy(string $challenge): void
    {
        $binaryChallenge = @hex2bin($challenge);

        if ($binaryChallenge === false) {
            throw new MikrotikAuthenticationException('Invalid legacy login challenge received from router.');
        }

        $response = md5("\x00".$this->config->password.$binaryChallenge);

        $replies = $this->executeSentence([
            '/login',
            '=name='.$this->config->username,
            '=response=00'.$response,
        ]);

        foreach ($replies as $reply) {
            if (in_array($reply['type'], ['!trap', '!fatal'], true)) {
                throw new MikrotikAuthenticationException($reply['attributes']['message'] ?? 'Legacy login failed.');
            }
        }

        $this->authenticated = true;

        $this->logger()->info('Connected to Mikrotik API with legacy authentication.', $this->config->logContext());
    }

    /**
     * @return list<array{type:string, attributes:array<string, string>}>
     */
    private function executeSentence(array $words): array
    {
        $this->writeSentence($words);

        return $this->readReplies();
    }

    private function writeSentence(array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord((string) $word);
        }

        $this->writeWord('');
    }

    private function writeWord(string $word): void
    {
        $payload = $word;
        $length = strlen($payload);

        if ($length < 0x80) {
            $prefix = chr($length);
        } elseif ($length < 0x4000) {
            $length |= 0x8000;
            $prefix = chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        } elseif ($length < 0x200000) {
            $length |= 0xC00000;
            $prefix = chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        } elseif ($length < 0x10000000) {
            $length |= 0xE0000000;
            $prefix = chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        } else {
            $prefix = "\xF0"
                .chr(($length >> 24) & 0xFF)
                .chr(($length >> 16) & 0xFF)
                .chr(($length >> 8) & 0xFF)
                .chr($length & 0xFF);
        }

        $this->writeAll($prefix.$payload);
    }

    /**
     * @return list<array{type:string, attributes:array<string, string>}>
     */
    private function readReplies(): array
    {
        $replies = [];

        while (true) {
            $sentence = $this->readSentence();

            if ($sentence === []) {
                continue;
            }

            $type = $sentence[0];
            $attributes = [];

            foreach (array_slice($sentence, 1) as $word) {
                $separator = strpos($word, '=', 1);

                if ($separator === false) {
                    $attributes[ltrim($word, '=')] = '';

                    continue;
                }

                $key = ltrim(substr($word, 0, $separator), '=');
                $attributes[$key] = substr($word, $separator + 1);
            }

            $replies[] = [
                'type' => $type,
                'attributes' => $attributes,
            ];

            if (in_array($type, ['!done', '!fatal'], true)) {
                break;
            }
        }

        return $replies;
    }

    /**
     * @return list<string>
     */
    private function readSentence(): array
    {
        $sentence = [];

        while (true) {
            $word = $this->readWord();

            if ($word === '') {
                break;
            }

            $sentence[] = $word;
        }

        return $sentence;
    }

    private function readWord(): string
    {
        $firstByte = ord($this->readBytes(1));

        if (($firstByte & 0x80) === 0x00) {
            $length = $firstByte;
        } elseif (($firstByte & 0xC0) === 0x80) {
            $length = (($firstByte & 0x3F) << 8) + ord($this->readBytes(1));
        } elseif (($firstByte & 0xE0) === 0xC0) {
            $length = (($firstByte & 0x1F) << 16)
                + (ord($this->readBytes(1)) << 8)
                + ord($this->readBytes(1));
        } elseif (($firstByte & 0xF0) === 0xE0) {
            $length = (($firstByte & 0x0F) << 24)
                + (ord($this->readBytes(1)) << 16)
                + (ord($this->readBytes(1)) << 8)
                + ord($this->readBytes(1));
        } else {
            $length = (ord($this->readBytes(1)) << 24)
                + (ord($this->readBytes(1)) << 16)
                + (ord($this->readBytes(1)) << 8)
                + ord($this->readBytes(1));
        }

        if ($length === 0) {
            return '';
        }

        return $this->readBytes($length);
    }

    private function writeAll(string $data): void
    {
        $remaining = $data;

        while ($remaining !== '') {
            $written = @fwrite($this->socket, $remaining);

            if ($written === false || $written === 0) {
                throw new MikrotikConnectionException('Failed to write data to Mikrotik socket.');
            }

            $remaining = substr($remaining, $written);
        }
    }

    private function readBytes(int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = @fread($this->socket, $length - strlen($buffer));

            if ($chunk === false) {
                throw new MikrotikConnectionException('Failed to read data from Mikrotik socket.');
            }

            if ($chunk === '') {
                $metadata = is_resource($this->socket) ? stream_get_meta_data($this->socket) : [];

                if (($metadata['timed_out'] ?? false) === true) {
                    throw new MikrotikConnectionException(sprintf(
                        'Timed out while waiting for Mikrotik response after %.1f second(s).',
                        $this->config->readTimeout,
                    ));
                }

                if (($metadata['eof'] ?? false) === true) {
                    throw new MikrotikConnectionException('Mikrotik socket closed unexpectedly.');
                }

                continue;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }

    /**
     * Sanitize parameters for Mikrotik API.
     * Mikrotik RouterOS API expects string values, so PHP booleans must be converted.
     */
    private function sanitizeParams(array $params): array
    {
        $sanitized = [];

        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $sanitized[$key] = $value ? 'true' : 'false';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Mask sensitive values before logging.
     * Passwords, secrets, and passphrases must not appear in log files.
     */
    private function sanitizeParamsForLog(array $params): array
    {
        $sensitiveKeys = ['password', 'pass', 'secret', 'passphrase'];
        $safe = $params;

        foreach ($sensitiveKeys as $key) {
            if (isset($safe[$key])) {
                $safe[$key] = '********';
            }
        }

        foreach ($safe as $k => $v) {
            if (is_string($v) && preg_match('/(=password=)(.*)$/i', $v)) {
                $safe[$k] = preg_replace('/(=password=)(.*)$/i', '$1********', $v);
            }
        }

        return $safe;
    }
}