<?php

namespace App\Http\Requests\Vid;

use App\Enums\VidStatus;
use App\Enums\VidType;
use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Rule;

class IndexVidRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'status' => $this->filled('status') ? strtolower(trim((string) $this->input('status'))) : null,
            'vid_type' => $this->filled('vid_type') ? strtolower(trim((string) $this->input('vid_type'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'router_id' => ['nullable', 'integer', 'exists:routers,id'],
            'status' => ['nullable', Rule::enum(VidStatus::class)],
            'vid_type' => ['nullable', Rule::enum(VidType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
