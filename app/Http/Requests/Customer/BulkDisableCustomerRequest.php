<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class BulkDisableCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_ids' => ['required', 'array', 'min:1', 'max:500'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:customers,id'],
        ];
    }
}
