<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AcceptsImportedGender;
use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRecordRequest extends FormRequest
{
    use AcceptsImportedGender;
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
            'center_id' => ['required', 'exists:centers,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'farmer_id' => ['nullable', 'exists:farmers,id'],
            'month_submitted' => ['nullable', 'date'],
            'client_name' => ['required', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', 'string', Rule::in(self::GENDERS)],
            'barangay' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'no_animals_served' => ['required', 'integer', 'min:0'],
            'date_conducted' => ['required', 'date'],
        ];
    }
}
