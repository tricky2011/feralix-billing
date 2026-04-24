<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use App\Http\Requests\AdminPanelRequest;
use App\Models\Service;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => $this->filled('category') ? Str::of((string) $this->input('category'))->trim()->squish()->value() : null,
            'priority' => $this->filled('priority') ? strtolower(trim((string) $this->input('priority'))) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'category' => ['required', 'string', 'max:100'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'description' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('service_id') || ! $this->filled('customer_id')) {
                return;
            }

            $service = Service::query()
                ->select(['id', 'customer_id'])
                ->find($this->integer('service_id'));

            if ($service !== null && (int) $service->customer_id !== $this->integer('customer_id')) {
                $validator->errors()->add('service_id', 'The selected service does not belong to the selected customer.');
            }
        });
    }
}
