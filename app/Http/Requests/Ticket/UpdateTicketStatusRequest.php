<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketStatus;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $status = $this->filled('status')
            ? strtolower(trim((string) $this->input('status')))
            : null;

        $status = match ($status) {
            'done' => TicketStatus::Resolved->value,
            'canceled', 'cancelled' => TicketStatus::Closed->value,
            default => $status,
        };

        $this->merge([
            'status' => $status,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
