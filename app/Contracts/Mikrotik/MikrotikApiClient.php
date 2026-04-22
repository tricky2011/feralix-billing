<?php

namespace App\Contracts\Mikrotik;

interface MikrotikApiClient
{
    /**
     * @return list<array<string, string>>
     */
    public function print(string $menuPath, array $properties = []): array;

    public function disconnect(): void;
}
