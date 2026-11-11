<?php

namespace App\Http\Requests;

use App\Enums\ProgramEnum;
use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMilkFeedingProgramRequest extends FormRequest
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
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'center_id' => ['sometimes', 'integer', 'exists:centers,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],

            'program' => ['sometimes', 'string', Rule::in(array_keys(ProgramEnum::labels()))],
            'year' => ['sometimes', 'integer', 'digits:4', 'between:2000,2100'],
            'funded_by' => ['nullable', 'string', 'max:32'],

            'region' => ['nullable', 'string', 'max:64'],
            'district' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:255'],

            'milk_packs' => ['nullable', 'integer', 'min:0'],
            'total_volume_requirements' => ['nullable', 'numeric', 'min:0'],
            'raw_milk_used' => ['nullable', 'numeric', 'min:0'],
            'whole_milk' => ['nullable', 'numeric', 'min:0'],
            'skimmed_milk' => ['nullable', 'numeric', 'min:0'],
            'sugar' => ['nullable', 'numeric', 'min:0'],
            'milk_type' => ['nullable', 'string', 'max:32'],
            'milk_subcategory' => ['nullable', 'string', 'max:32'],
            'price_per_milk_pack' => ['nullable', 'numeric', 'min:0'],

            'feeding_days' => ['nullable', 'integer', 'min:0'],
            'number_of_beneficiaries' => ['nullable', 'integer', 'min:0'],

            'total_contract_amount' => ['nullable', 'numeric', 'min:0'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'gross_income' => ['nullable', 'numeric', 'min:0'],
            'liquidation_date' => ['nullable', 'date'],

            'mode_of_procurement' => ['nullable', 'string', 'max:64'],
            'moa_signing_date' => ['nullable', 'date'],
            'fund_transfer_date' => ['nullable', 'date'],
            'date_started' => ['nullable', 'date'],
            'date_completed' => ['nullable', 'date'],
        ];
    }
}
