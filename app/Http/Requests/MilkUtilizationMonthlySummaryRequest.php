<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MilkUtilizationMonthlySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'program' => ['required', 'string', 'in:ih,cbed'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'center_code' => ['nullable', 'string', 'exists:centers,code'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'lactating_animals' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'production' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'received' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fed_to_calves' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'value_added_products' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'milk_feeding' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
