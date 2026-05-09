<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Models\Router;
use Illuminate\Log\LogManager;

class MikrotikPppoeSecretService
{
    public function __construct(
        private readonly MikrotikApiClientFactory $clientFactory,
        private readonly LogManager $logManager,
    ) {}

    /**
     * Create PPPoE secret on MikroTik router
     *
     * @return array{success: bool, mikrotik_id: string|null, message: string}
     */
    public function createSecret(
        Router $router,
        string $username,
        string $password,
        ?string $profile = null,
        ?string $comment = null,
        bool $disabled = false,
    ): array {
        try {
            $client = $this->clientFactory->forRouter($router);
            $profile ??= config('mikrotik.pppoe.default_profile', 'default');

            // Check if secret already exists
            $existing = $client->print('/ppp/secret', ['.id', 'name'], ['name' => $username]);

            if (! empty($existing)) {
                $this->logger()->info('PPPoE secret already exists, skipping creation.', [
                    'router_id' => $router->id,
                    'router_name' => $router->name,
                    'username' => $username,
                ]);

                $client->disconnect();

                return [
                    'success' => true,
                    'mikrotik_id' => $existing[0]['.id'] ?? null,
                    'message' => 'Secret already exists.',
                ];
            }

            $attributes = [
                'name' => $username,
                'password' => $password,
                'service' => 'pppoe',
                'profile' => $profile,
                'comment' => $comment ?? '',
                'disabled' => $disabled ? 'yes' : 'no',
            ];

            $client->add('/ppp/secret', $attributes);

            $created = $client->print('/ppp/secret', ['.id'], ['name' => $username]);

            $this->logger()->info('PPPoE secret created on MikroTik.', [
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
                'message' => 'Secret created successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to create PPPoE secret.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            if (isset($client)) {
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
     * Remove PPPoE secret from MikroTik router
     *
     * @return array{success: bool, message: string}
     */
    public function removeSecret(Router $router, string $username): array
    {
        try {
            $client = $this->clientFactory->forRouter($router);

            // Find the secret
            $existing = $client->print('/ppp/secret', ['.id', 'name'], ['name' => $username]);

            if (empty($existing)) {
                $this->logger()->info('PPPoE secret not found, skipping removal.', [
                    'router_id' => $router->id,
                    'router_name' => $router->name,
                    'username' => $username,
                ]);

                $client->disconnect();

                return [
                    'success' => true,
                    'message' => 'Secret not found.',
                ];
            }

            $client->remove('/ppp/secret', $existing[0]['.id']);

            $this->logger()->info('PPPoE secret removed from MikroTik.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'message' => 'Secret removed successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to remove PPPoE secret.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if (isset($client)) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Update PPPoE secret profile (for isolation)
     *
     * @return array{success: bool, message: string}
     */
    public function setSecretProfile(Router $router, string $username, string $profile): array
    {
        try {
            $client = $this->clientFactory->forRouter($router);

            // Find the secret
            $existing = $client->print('/ppp/secret', ['.id', 'name'], ['name' => $username]);

            if (empty($existing)) {
                $client->disconnect();

                return [
                    'success' => false,
                    'message' => 'Secret not found.',
                ];
            }

            $client->set('/ppp/secret', $existing[0]['.id'], [
                'profile' => $profile,
            ]);

            $this->logger()->info('PPPoE secret profile updated.', [
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
            $this->logger()->error('Failed to update PPPoE secret profile.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if (isset($client)) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Update PPPoE secret password
     *
     * @return array{success: bool, message: string}
     */
    public function setSecretPassword(Router $router, string $username, string $newPassword): array
    {
        try {
            $client = $this->clientFactory->forRouter($router);

            // Find the secret
            $existing = $client->print('/ppp/secret', ['.id', 'name'], ['name' => $username]);

            if (empty($existing)) {
                $client->disconnect();

                return [
                    'success' => false,
                    'message' => 'Secret not found.',
                ];
            }

            $client->set('/ppp/secret', $existing[0]['.id'], [
                'password' => $newPassword,
            ]);

            $this->logger()->info('PPPoE secret password updated.', [
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
            $this->logger()->error('Failed to update PPPoE secret password.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if (isset($client)) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Update PPPoE secret comment
     *
     * @return array{success: bool, message: string}
     */
    public function setSecretComment(Router $router, string $username, string $comment): array
    {
        try {
            $client = $this->clientFactory->forRouter($router);

            // Find the secret
            $existing = $client->print('/ppp/secret', ['.id', 'name'], ['name' => $username]);

            if (empty($existing)) {
                $client->disconnect();

                return [
                    'success' => false,
                    'message' => 'Secret not found.',
                ];
            }

            $client->set('/ppp/secret', $existing[0]['.id'], [
                'comment' => $comment,
            ]);

            $this->logger()->info('PPPoE secret comment updated.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'comment' => $comment,
            ]);

            $client->disconnect();

            return [
                'success' => true,
                'message' => 'Comment updated successfully.',
            ];
        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to update PPPoE secret comment.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if (isset($client)) {
                $client->disconnect();
            }

            return [
                'success' => false,
                'message' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Enable or disable PPPoE secret (used for prepaid activation flow).
     *
     * @return array{success: bool, message: string}
     */
    public function setSecretEnabled(Router $router, string $username, bool $enabled): array
    {
        try {
            $client = $this->clientFactory->forRouter($router);

            $existing = $client->print('/ppp/secret', ['.id', 'name', 'disabled'], ['name' => $username]);

            if (empty($existing)) {
                $client->disconnect();

                return ['success' => false, 'message' => 'Secret not found.'];
            }

            $client->set('/ppp/secret', $existing[0]['.id'], [
                'disabled' => $enabled ? 'no' : 'yes',
            ]);

            $this->logger()->info('PPPoE secret ' . ($enabled ? 'enabled' : 'disabled') . '.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
            ]);

            $client->disconnect();

            return ['success' => true, 'message' => $enabled ? 'Secret enabled.' : 'Secret disabled.'];

        } catch (\Throwable $throwable) {
            $this->logger()->error('Failed to ' . ($enabled ? 'enable' : 'disable') . ' PPPoE secret.', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $username,
                'message' => $throwable->getMessage(),
            ]);

            if (isset($client)) {
                $client->disconnect();
            }

            return ['success' => false, 'message' => $throwable->getMessage()];
        }
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
