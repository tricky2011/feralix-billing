<?php

namespace App\Http\Requests\PonPort;

use App\Http\Requests\AdminPanelRequest;

class IndexPonPortRequest extends AdminPanelRequest
{ array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}