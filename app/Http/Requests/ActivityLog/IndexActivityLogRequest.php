<?php

namespace App\Http\Requests\ActivityLog;

use App\Http\Requests\AdminPanelRequest;

class IndexActivityLogRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'action' => $this->filled('action') ? trim((string) $this->input('action')) : null,
            'module' => $this->filled('module') ? trim((string) $this->input('module')) : null,
            'created_from' => $this->filled('created_from') ? trim((string) $this->input('created_from')) : null,
            'created_to' => $this->filled('created_to') ? trim((string) $this->input('created_to')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:100'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
