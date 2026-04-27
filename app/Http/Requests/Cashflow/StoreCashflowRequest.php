<?php

namespace App\Http\Requests\Cashflow;

use App\Http\Requests\AdminPanelRequest;
use App\Models\CashflowCategory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCashflowRequest extends AdminPanelRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->filled('type') ? strtolower(trim((string) $this->input('type'))) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'category_id' => ['nullable', 'integer', 'exists:cashflow_categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('category_id') || ! $this->filled('type')) {
                return;
            }

            $category = CashflowCategory::query()
                ->select(['id', 'type'])
                ->find($this->integer('category_id'));

            if ($category === null) {
                return;
            }

            if ($category->type !== $this->string('type')->toString()) {
                $validator->errors()->add('category_id', 'Category type must match cashflow type.');
            }
        });
    }
}
