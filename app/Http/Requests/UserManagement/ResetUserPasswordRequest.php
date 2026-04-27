<?php

namespace App\Http\Requests\UserManagement;

use App\Http\Requests\AdminPanelRequest;

class ResetUserPasswordRequest extends AdminPanelRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
