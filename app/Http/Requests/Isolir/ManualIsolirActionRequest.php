<?php

namespace App\Http\Requests\Isolir;

use App\Http\Requests\AdminPanelRequest;
use Closure;
use Illuminate\Validation\Rule;

class ManualIsolirActionRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $userId = $this->input('user_id');

        if (is_string($userId)) {
            $userId = trim($userId);
        }

        $this->merge([
            'user_id' => $userId,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'router_id' => ['required', 'integer', Rule::exists('routers', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'user_id' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_scalar($value)) {
                        $fail('The selected user is invalid.');

                        return;
                    }

                    $normalized = trim((string) $value);

                    if ($normalized === '') {
                        $fail('The selected user is invalid.');

                        return;
                    }

                    if (mb_strlen($normalized) > 150) {
                        $fail('The selected user is too long.');
                    }
                },
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
