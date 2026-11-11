<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Foundation\Http\FormRequest;

class MilkUtilizationRequest extends FormRequest
{
    use ForcesCenterForCenterScopedUser;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->forceCenterForCenterScopedUser();
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'section' => ['required', 'string', 'in:institutional,cbed'],
            'center' => ['nullable', 'integer', 'exists:centers,id'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ];
    }
}
