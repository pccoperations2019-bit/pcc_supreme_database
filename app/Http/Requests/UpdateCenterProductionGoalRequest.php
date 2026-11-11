<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCenterProductionGoalRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'center_id' => ['required', 'integer', 'exists:centers,id'],
            'year' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'program' => ['required', 'string', 'in:ih,cbed'],
            'milk_production_goal' => ['nullable', 'numeric', 'min:0'],
            'meat_meat_equivalent_goal' => ['nullable', 'numeric', 'min:0'],
            'manure_based_goal' => ['nullable', 'numeric', 'min:0'],
            'hide_production_goals' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
