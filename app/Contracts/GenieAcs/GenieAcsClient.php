<?php

namespace App\Contracts\GenieAcs;

interface GenieAcsClient
{
    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $projection
     * @return list<array<string, mixed>>
     */
    public function findDevices(array $query, array $projection = [], ?int $limit = null): array;
}
