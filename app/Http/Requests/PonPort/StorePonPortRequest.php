<?php

namespace App\Http\Requests\PonPort;

use App\Http\Requests\AdminPanelRequest;

class StorePonPortRequest extends AdminPanelRequest
{ array
    {
        return [
            'port_number' => ['required', 'integer', 'min:1', 'max:32'],
            'name' => ['nullable', 'string', 'max:50'],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}