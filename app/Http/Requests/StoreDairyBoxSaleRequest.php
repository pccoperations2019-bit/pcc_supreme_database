<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreDairyBoxSaleRequest extends FormRequest
{
    use ForcesCenterForCenterScopedUser;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->forceCenterForCenterScopedUser();
    }

    public function rules(): array
    {
        return [
            'dairy_box_id' => ['required', 'exists:dairy_boxes,id'],
            'center_id' => ['required', 'exists:centers,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'total_sales' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'dairy_box_id.required' => 'The dairy box field is required.',
            'dairy_box_id.exists' => 'The selected dairy box is invalid.',
            'center_id.required' => 'The center field is required.',
            'center_id.exists' => 'The selected center is invalid.',
            'partner_id.exists' => 'The selected partner is invalid.',
            'period_start.required' => 'The period start field is required.',
            'period_end.required' => 'The period end field is required.',
            'period_end.after_or_equal' => 'The period end must be on or after the period start.',
            'year.integer' => 'The year must be an integer.',
            'year.min' => 'The year must be at least 2000.',
            'year.max' => 'The year may not be greater than 2100.',
            'month.integer' => 'The month must be an integer.',
            'month.min' => 'The month must be at least 1.',
            'month.max' => 'The month may not be greater than 12.',
            'total_sales.numeric' => 'The total sales must be a number.',
            'total_sales.min' => 'The total sales must be at least 0.',
        ];
    }
}
