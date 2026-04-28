<?php

namespace App\Services\Mikrotik;

use App\Contracts\Mikrotik\MikrotikApiClient;
use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Enums\ServiceRouterOperationLogAction;
use App\Models\Router;
use Illuminate\Log\LogManager;

class MikrotikAddressListService
{
    public function __construct(
        private readonly MikrotikApiClientFactory $clientFactory,
        private readonly LogManager $logManager,
    ) {}

    /**
     * @return array{action:string, router_item_id:string|null, matched:int}
     */
    public function ensureAddressListed(Router $router, string $listName, string $address, ?string $comment = null): array
    {
        $client = $this->clientFactory->forRouter($router);

        try {
            $existing = $this->findEntries($client, $listName, $address);

            if ($existing !== []) {
                $this->logger()->info('Mikrotik address-list isolate skipped because entry already exists.', [
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                    'address_list_name' => $listName,
                    'target_address' => $address,
                    'matched_entries' => count($existing),
                ]);

                return [
                    'action' => ServiceRouterOperationLogAction::AlreadyPresent->value,
                    'router_item_id' => $existing[0]['.id'] ?? null,
                    'matched' => count($existing),
                ];
            }

            $client->add('/ip/firewall/address-list', [
                'list' => $listName,
                'address' => $address,
                'comment' => $comment,
            ]);

            $created = $this->findEntries($client, $listName, $address);

            $this->logger()->info('Mikrotik address-list isolate applied.', [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'address_list_name' => $listName,
                'target_address' => $address,
                'matched_entries' => count($created),
            ]);

            return [
                'action' => ServiceRouterOperationLogAction::Added->value,
                'router_item_id' => $created[0]['.id'] ?? null,
                'matched' => count($created),
            ];
        } finally {
            $client->disconnect();
        }
    }

    /**
     * @param  list<string>  $legacyCommentPatterns  Partial comment substrings to match legacy entries (backward compatibility)
     * @return array{action:string, router_item_id:string|null, matched:int}
     */
    public function ensureAddressRemoved(Router $router, string $listName, string $address, array $legacyCommentPatterns = []): array
    {
        $client = $this->clientFactory->forRouter($router);

        try {
            $byAddress = $this->findEntries($client, $listName, $address);
            $byComment = $legacyCommentPatterns !== []
                ? $this->findEntriesByCommentPatterns($client, $listName, $legacyCommentPatterns)
                : [];

            $existing = $this->mergeUniqueById($byAddress, $byComment);

            if ($existing === []) {
                $this->logger()->info('Mikrotik address-list release skipped because entry is already absent.', [
                    'router_id' => $router->id,
                    'router_code' => $router->router_code,
                    'address_list_name' => $listName,
                    'target_address' => $address,
                ]);

                return [
                    'action' => ServiceRouterOperationLogAction::AlreadyAbsent->value,
                    'router_item_id' => null,
                    'matched' => 0,
                ];
            }

            foreach ($existing as $entry) {
                if (! isset($entry['.id']) || $entry['.id'] === '') {
                    continue;
                }

                $client->remove('/ip/firewall/address-list', $entry['.id']);
            }

            $this->logger()->info('Mikrotik address-list release applied.', [
                'router_id' => $router->id,
                'router_code' => $router->router_code,
                'address_list_name' => $listName,
                'target_address' => $address,
                'removed_entries' => count($existing),
            ]);

            return [
                'action' => ServiceRouterOperationLogAction::Removed->value,
                'router_item_id' => $existing[0]['.id'] ?? null,
                'matched' => count($existing),
            ];
        } finally {
            $client->disconnect();
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function findEntries(MikrotikApiClient $client, string $listName, string $address): array
    {
        return $client->print(
            '/ip/firewall/address-list',
            ['.id', 'list', 'address', 'comment'],
            [
                'list' => $listName,
                'address' => $address,
            ],
        );
    }

    /**
     * Fetch all entries in the list and filter client-side by partial comment match.
     *
     * @param  list<string>  $patterns
     * @return list<array<string, string>>
     */
    private function findEntriesByCommentPatterns(MikrotikApiClient $client, string $listName, array $patterns): array
    {
        $all = $client->print(
            '/ip/firewall/address-list',
            ['.id', 'list', 'address', 'comment'],
            ['list' => $listName],
        );

        return array_values(array_filter(
            $all,
            static function (array $entry) use ($patterns): bool {
                $comment = $entry['comment'] ?? '';
                foreach ($patterns as $pattern) {
                    if ($pattern !== '' && str_contains($comment, $pattern)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /**
     * Merge multiple entry lists, deduplicating by .id.
     *
     * @param  list<array<string, string>>  ...$lists
     * @return list<array<string, string>>
     */
    private function mergeUniqueById(array ...$lists): array
    {
        $merged = [];
        $seen = [];

        foreach (array_merge(...$lists) as $entry) {
            $id = $entry['.id'] ?? '';
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $merged[] = $entry;
        }

        return $merged;
    }

    private function logger()
    {
        $channel = (string) (config('mikrotik.logging.channel') ?: config('logging.default', 'stack'));

        return $this->logManager->channel($channel);
    }
}
