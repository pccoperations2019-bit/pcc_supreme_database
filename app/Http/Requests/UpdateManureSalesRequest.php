<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateManureSalesRequest extends FormRequest
{
    use ForcesCenterForCenterScopedUser;

    /**
     * Determine if the user is authorized to make this request.
     */
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
            'center_id' => ['required', 'exists:centers,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'farmer_id' => ['nullable', 'exists:farmers,id'],
            'program' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'total_sales' => ['nullable', 'numeric', 'min:0'],
            'monthly_data' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'center_id.required' => 'The center field is required.',
            'center_id.exists' => 'The selected center is invalid.',
            'partner_id.exists' => 'The selected partner is invalid.',
            'program.required' => 'The program field is required.',
            'year.required' => 'The year field is required.',
            'year.integer' => 'The year must be an integer.',
            'year.min' => 'The year must be at least 2000.',
            'year.max' => 'The year may not be greater than 2100.',
            'month.required' => 'The month field is required.',
            'month.integer' => 'The month must be an integer.',
            'month.min' => 'The month must be at least 1.',
            'month.max' => 'The month may not be greater than 12.',
            'quarter.required' => 'The quarter field is required.',
            'quarter.integer' => 'The quarter must be an integer.',
            'quarter.min' => 'The quarter must be at least 1.',
            'quarter.max' => 'The quarter may not be greater than 4.',
            'total_volume.required' => 'The total volume field is required.',
            'total_volume.numeric' => 'The total volume must be a number.',
            'total_volume.min' => 'The total volume must be at least 0.',
        ];
    }
}
