<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Models\Router;
use Illuminate\Log\LogManager;

/**
 * Manages hotspot users on MikroTik routers via /ip/hotspot/user API.
 *
 * MikroTik hotspot user paths:
 * - ROS v6: /ip/hotspot/user
 * - ROS v7: /ip/hotspot/user
 *
 * Key profile attributes used:
 * - rate-limit: "RX/TX" in bps (e.g. "10M/10M")
 * - session-timeout: max session duration in seconds
 * - shared-users: concurrent login limit
 * - idle-timeout: max idle time before disconnect
 * - address-pool: DHCP pool for framed IP assignment
 */
class MikrotikHotspotSecretService
{
    public function __construct(
        private readonly MikrotikApiClientFactory $clientFactory,
        private readonly LogManager $logManager,
    ) {}

    /**
     * Create hotspot user on MikroTik router.
     *
     * @return array{success: bool, mikrotik_id: string|null, message: string}
     */
    public function createUser(
        Router $router,
        string $username,
        string $password,
        ?string $profile = null,
        ?string $comment = null,
        int $sharedUsers = 1,
        bool $disabled = false,
    ): array {
        $client = null;

        try {
            $client = $this->clientFactory->forRouter($router);
            $profile ??= config('mikrotik.hotspot.default_profile', 'default');

            // Check if user already exists
            $existing = $client->print('/ip/hotspot/user', ['.id', 'name'], ['name' => $username]);

            if (! empty($existing)) {
                $this->logger()->info('Hotspot user already exists, skipping creation.', [
                    'router_id' => $router->id,
                    'router_name' => $router->name,
                    'username' => $username,
                ]);

                $client->disconnect();

                return [
                    'success' => true,
                    'mikrotik_id' => $existing[0]['.id'] ?? null,
                    'message' => 'User already exists.',
                ];
            }

            $attributes = [
                'name' => $username,
                'password' => $password,
                'profile' => $profile,
                'comment' => $comment ?? '',
                'shared-users' => (string) $sharedUsers,
                'disabled' => $disabled ? 'yes' : 'no',
            ];

            $client->add('/ip/hotspot/user', $attributes);

            // Verify creation
            $created = $client->print('/ip/hotspot/user', ['.id'], ['name' => $username]);

            $this->logger()->info('Hotspot user created on MikroTik.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'profile' => $profile,
                'mikrotik_id' => $created[0]['.id'] ?? null,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'mikrotik_id' => $created[0]['.id'] ?? null,
                'message' => 'User created successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to create hotspot user.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if ($client !== null) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'mikrotik_id' => null,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Remove hotspot user from MikroTik router.
     *
     * @return array{success: bool, message: string}
     */
    public function removeUser(Router $router, string $username): array
    {
        $client = null;

        try {
            $client = $this->clientFactory->forRouter($router);

            $existing = $client->print('/ip/hotspot/user', ['.id', 'name'], ['name' => $username]);

            if (empty($existing)) {
                $this->logger()->info('Hotspot user not found, skipping removal.', [
                    'router_id' => $router->id,
                    'router_name' => $router->name,
                    'username' => $username,
                ]);

                $client->disconnect();

                return [
                    'success' => true,
                    'message' => 'User not found.',
                ];
            }

            $client->remove('/ip/hotspot/user', $existing[0]['.id']);

            $this->logger()->info('Hotspot user removed from MikroTik.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'message' => 'User removed successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to remove hotspot user.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if ($client !== null) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Update hotspot user profile.
     *
     * @return array{success: bool, message: string}
     */
    public function setUserProfile(Router $router, string $username, string $profile): array
    {
        $client = null;

        try {
            $client = $this->clientFactory->forRouter($router);

            $existing = $client->print('/ip/hotspot/user', ['.id', 'name'], ['name' => $username]);

            if (empty($existing)) {
                $client->disconnect();

                return [
                    'success' => false,
                    'message' => 'User not found.',
                ];
            }

            $client->set('/ip/hotspot/user', $existing[0]['.id'], [
                'profile' => $profile,
            ]);

            $this->logger()->info('Hotspot user profile updated.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'profile' => $profile,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'message' => 'Profile updated successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to update hotspot user profile.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if ($client !== null) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Update hotspot user password.
     *
     * @return array{success: bool, message: string}
     */
    public function setUserPassword(Router $router, string $username, string $newPassword): array
    {
        $client = null;

        try {
            $client = $this->clientFactory->forRouter($router);

            $existing = $client->print('/ip/hotspot/user', ['.id', 'name'], ['name' => $username]);

            if (empty($existing)) {
                $client->disconnect();

                return [
                    'success' => false,
                    'message' => 'User not found.',
                ];
            }

            $client->set('/ip/hotspot/user', $existing[0]['.id'], [
                'password' => $newPassword,
            ]);

            $this->logger()->info('Hotspot user password updated.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'message' => 'Password updated successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to update hotspot user password.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if ($client !== null) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Enable or disable hotspot user.
     *
     * @return array{success: bool, message: string}
     */
    public function setUserEnabled(Router $router, string $username, bool $enabled): array
    {
        $client = null;

        try {
            $client = $this->clientFactory->forRouter($router);

            $existing = $client->print('/ip/hotspot/user', ['.id', 'name'], ['name' => $username]);

            if (empty($existing)) {
                $client->disconnect();

                return [
                    'success' => false,
                    'message' => 'User not found.',
                ];
            }

            $client->set('/ip/hotspot/user', $existing[0]['.id'], [
                'disabled' => $enabled ? 'no' : 'yes',
            ]);

            $this->logger()->info('Hotspot user ' . ($enabled ? 'enabled' : 'disabled') . '.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'message' => $enabled ? 'User enabled.' : 'User disabled.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to ' . ($enabled ? 'enable' : 'disable') . ' hotspot user.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if ($client !== null) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Get all hotspot users from MikroTik router.
     *
     * @return array<int, array<string, string>>
     */
    public function listUsers(Router $router): array
    {
        $client = null;

        try {
            $client = $this->clientFactory->forRouter($router);
            $users = $client->print('/ip/hotspot/user', ['.id', 'name', 'password', 'profile', 'comment', 'disabled', 'shared-users', 'uptime', 'bytes-in', 'bytes-out']);
            $client->disconnect();

            return $users;
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to list hotspot users.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'message' => $throwable->getMessage(),
            ]);

            if ($client !== null) {
                $client->disconnect();
            }

            return [];
        }
    }

    /**
     * Get hotspot profiles from MikroTik router.
     *
     * @return array<int, array<string, string>>
     */
    public function listProfiles(Router $router): array
    {
        $client = null;

        try {
            $client = $this->clientFactory->forRouter($router);
            $profiles = $client->print('/ip/hotspot/user/profile', ['.id', 'name', 'rate-limit', 'session-timeout', 'idle-timeout', 'shared-users', 'address-pool']);
            $client->disconnect();

            return $profiles;
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to list hotspot profiles.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'message' => $throwable->getMessage(),
            ]);

            if ($client !== null) {
                $client->disconnect();
            }

            return [];
        }
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
