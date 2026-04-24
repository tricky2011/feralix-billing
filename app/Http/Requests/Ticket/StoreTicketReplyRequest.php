<?php

namespace App\Http\Requests\Ticket;

use App\Http\Requests\AdminPanelRequest;

class StoreTicketReplyRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => $this->filled('body') ? trim((string) $this->input('body')) : null,
            'is_internal' => $this->boolean('is_internal'),
        ]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ];
    }
}
