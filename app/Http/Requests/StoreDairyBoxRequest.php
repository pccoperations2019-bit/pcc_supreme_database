<?php

namespace App\Http\Requests;

use App\Enums\ComplianceStatusEnum;
use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDairyBoxRequest extends FormRequest
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
            'dairy_box_id' => ['required', 'string', 'max:255', 'unique:dairy_boxes,dairy_box_id'],
            'business_name' => ['required', 'string', 'max:255'],
            'manager' => ['nullable', 'string', 'max:255'],
            'dairy_box_focal' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'center_id' => ['required', 'integer', 'exists:centers,id'],
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'status' => ['required', 'string', 'max:255'],
            'launched_date' => ['nullable', 'date'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'kadiwa_integration' => ['nullable', 'string', 'in:yes,no'],
            'milk_feeding_engagement' => ['nullable', 'string', 'in:yes,no'],
            'bir_registered' => ['nullable', 'string', Rule::in(array_keys(ComplianceStatusEnum::labels()))],
            'fda_lto' => ['nullable', 'string', Rule::in(array_keys(ComplianceStatusEnum::labels()))],
            'cso_accreditation' => ['nullable', 'string', Rule::in(array_keys(ComplianceStatusEnum::labels()))],
            'philgeps' => ['nullable', 'string', Rule::in(array_keys(ComplianceStatusEnum::labels()))],
            'ffedis' => ['nullable', 'string', Rule::in(array_keys(ComplianceStatusEnum::labels()))],
            'fund_source_outlet' => ['nullable', 'string'],
            'fund_source_equipment' => ['nullable', 'string'],
            'fund_source_processing_plant' => ['nullable', 'string'],

            'land_ownership' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'start_of_construction' => ['nullable', 'date'],
            'date_of_completion' => ['nullable', 'date'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'date_inaugurated' => ['nullable', 'date'],
            'metadata' => ['nullable', 'json'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            // Compliance documents are uploaded to storage before this request and persisted here with the new DairyBox.
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*.title' => ['required', 'string', 'max:255'],
            'documents.*.file_name' => ['required', 'string', 'max:255'],
            'documents.*.file_key' => ['required', 'string', 'max:255'],
            'documents.*.file_type' => ['required', 'string', 'max:100'],
            'documents.*.file_size' => ['required', 'integer', 'min:1'],
            'documents.*.description' => ['nullable', 'string'],
            'documents.*.registration_number' => ['nullable', 'string', 'max:255'],
            'documents.*.validity' => ['nullable', 'date'],
            'documents.*.document_type' => ['required', 'string', Rule::in([
                'bir',
                'fda-lto',
                'cso',
                'philgeps',
                'ffedis',
            ])],
            'documents.*.compliance_status' => ['nullable', 'string', Rule::in(array_keys(ComplianceStatusEnum::labels()))],
        ];
    }

    public function messages(): array
    {
        return [
            'dairy_box_id.required' => 'This field is required.',
            'business_name.required' => 'This field is required.',
            'center_id.required' => 'This field is required.',
            'partner_id.required' => 'This field is required.',
            'status.required' => 'This field is required.',
        ];
    }
}
