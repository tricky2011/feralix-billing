<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\AdminPanelRequest;

class BulkDisableCustomerRequest extends AdminPanelRequest
{
    public function rules(): array
    {
        return [
            'customer_ids' => ['required', 'array', 'min:1', 'max:500'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
        ];
    }
}
