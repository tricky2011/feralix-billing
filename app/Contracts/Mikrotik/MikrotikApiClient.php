<?php

namespace App\Contracts\Mikrotik;

interface MikrotikApiClient
{
    /**
     * @return list<array<string, string>>
     */
    public function print(string $menuPath, array $properties = [], array $where = []): array;

    public function add(string $menuPath, array $attributes = []): void;

    public function remove(string $menuPath, string $id): void;

    public function disconnect(): void;
}
