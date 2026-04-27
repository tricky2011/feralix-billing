<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatabaseSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => 'current',
            'host' => $this->resource['host'] ?? null,
            'port' => $this->resource['port'] ?? null,
            'database' => $this->resource['database'] ?? null,
            'username' => $this->resource['username'] ?? null,
            'has_password' => (bool) ($this->resource['has_password'] ?? false),
        ];
    }
}
