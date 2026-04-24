<?php

namespace App\Http\Requests\RouterScope;

use App\Http\Requests\AdminPanelRequest;
use Illuminate\Validation\Validator;

class UpdateRouterScopeRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $isSpecial = $this->input('is_special');

        $this->merge([
            'scope_name' => $this->filled('scope_name') ? trim((string) $this->input('scope_name')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'is_special' => match (true) {
                $isSpecial === 'true', $isSpecial === '1', $isSpecial === 1, $isSpecial === true => true,
                $isSpecial === 'false', $isSpecial === '0', $isSpecial === 0, $isSpecial === false => false,
                default => $isSpecial,
            },
        ]);
    }

    public function rules(): array
    {
        return [
            'router_id' => ['required', 'integer', 'exists:routers,id'],
            'scope_name' => ['required', 'string', 'max:100'],
            'monitor_vid' => ['required', 'integer', 'between:1,4094'],
            'vid_start' => ['required', 'integer', 'between:1,4094'],
            'vid_end' => ['required', 'integer', 'between:1,4094', 'gte:vid_start'],
            'is_special' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('monitor_vid') || ! $this->filled('vid_start') || ! $this->filled('vid_end')) {
                return;
            }

            $monitorVid = (int) $this->input('monitor_vid');
            $vidStart = (int) $this->input('vid_start');
            $vidEnd = (int) $this->input('vid_end');

            if ($monitorVid < $vidStart || $monitorVid > $vidEnd) {
                $validator->errors()->add('monitor_vid', 'The monitor VID must be within the VLAN scope range.');
            }
        });
    }
}
