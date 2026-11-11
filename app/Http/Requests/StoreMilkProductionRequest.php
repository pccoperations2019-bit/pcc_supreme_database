<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreMilkProductionRequest extends FormRequest
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
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'farmer_id' => ['nullable', 'integer', 'exists:farmers,id'],
            'program' => ['required', 'string', 'in:ih,cbed'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'collection_date' => ['nullable', 'date'],
            'total_volume_produced' => ['required', 'numeric', 'min:0'],
        ];
    }
}
