<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketAssignmentMode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexTicketRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
            'priority' => $this->filled('priority') ? strtolower(trim((string) $this->input('priority'))) : null,
            'assignment_mode' => $this->filled('assignment_mode') ? strtolower(trim((string) $this->input('assignment_mode'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'assignment_mode' => ['nullable', Rule::enum(TicketAssignmentMode::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
