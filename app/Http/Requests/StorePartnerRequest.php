<?php

namespace App\Http\Requests;

use App\Enums\ComplianceStatusEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255', 'unique:partners,code'],
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'date_established' => ['nullable', 'date'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'telephone_number' => ['nullable', 'string', 'max:255'],
            'email_address' => ['nullable', 'email', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'partnership_type' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', Rule::in(array_column(ProgramEnum::cases(), 'value'))],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProfileStatusEnum::labels()))],
            'total_composition' => ['nullable', 'integer', 'min:0'],
            'male_count' => ['nullable', 'integer', 'min:0'],
            'female_count' => ['nullable', 'integer', 'min:0'],
            'avg_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'max:2048'],
            // Address fields morph
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'regex:/^\d{4}$/'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            // Animal Inventory (fixed repeating table)
            'animal_inventory' => ['nullable', 'array', 'max:12'],
            'animal_inventory.*.classification' => ['required', 'string', Rule::in([
                'Cow Lactating',
                'Cow Lactating Pregnant',
                'Cow Dry Pregnant',
                'Cow Dry',
                'Heifer Pregnant',
                'Heifer',
                'Sr. Bull (more than 3 yrs)',
                'Jr. Bull (2-3 yrs.)',
                'Yearling Female (1 yr - 2 yrs)',
                'Yearling Male (1 yr - 2 yrs)',
                'Female Calf (less than 1 year)',
                'Male Calf (less than 1 year)',
            ])],
            'animal_inventory.*.count' => ['nullable', 'integer', 'min:0'],
            'animal_inventory.*.breed' => ['nullable', 'string', Rule::in(['Native', 'CB', 'Purebred'])],
            'animal_inventory.*.remarks' => ['nullable', 'string', 'max:5000'],
            // Extended profile fields (stored in metadata)
            'date_of_enrollment' => ['nullable', 'date'],
            'incharge' => ['nullable', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_shortname' => ['nullable', 'string', 'max:255'],
            'assistance_status' => ['nullable', 'string', 'max:255'],
            'date_assisted' => ['nullable', 'date'],
            'partner_businesstype' => ['nullable', 'string', 'max:255'],
            'registrationtype' => ['nullable', 'string', 'max:255'],
            'registration_no' => ['nullable', 'string', 'max:255'],
            'registration_date' => ['nullable', 'date'],
            'purokzone' => ['nullable', 'string', 'max:255'],
            'name_of_manager' => ['nullable', 'string', 'max:255'],
            'manager_contact_no' => ['nullable', 'string', 'max:255'],
            'manager_email' => ['nullable', 'email', 'max:255'],
            'contact_person_contact_no' => ['nullable', 'string', 'max:255'],
            'contact_person_email' => ['nullable', 'email', 'max:255'],
            'bodcount' => ['nullable', 'integer', 'min:0'],
            'bodschedule' => ['nullable', 'string', 'max:255'],
            'active_count' => ['nullable', 'integer', 'min:0'],
            'main_business' => ['nullable', 'string', 'max:255'],
            'other_line_of_business' => ['nullable', 'string', 'max:255'],
            'businesscount' => ['nullable', 'integer', 'min:0'],
            'status_google_map' => ['nullable', 'string', 'max:255'],
            'geo_tag_image' => ['nullable', 'string', 'max:255'],
            'google_map_link' => ['nullable', 'url'],
            'ndaltocertificationstatus' => ['nullable', 'string', 'max:255'],
            'ndaltocertno' => ['nullable', 'string', 'max:255'],
            'ndaltocertno_validity' => ['nullable', 'date'],
            'fdaltocertificationstatus' => ['nullable', 'string', 'max:255'],
            'fdaltocertno' => ['nullable', 'string', 'max:255'],
            'fdaltocertno_validity' => ['nullable', 'date'],
            'philgepsstatus' => ['nullable', 'string', 'max:255'],
            'philgepsstatus_validity' => ['nullable', 'date'],
            'csocertificationstatus' => ['nullable', 'string', 'max:255'],
            'cso_validity' => ['nullable', 'date'],
            'ffedisstatus' => ['nullable', 'string', 'max:255'],
            'ffedis_certno' => ['nullable', 'string', 'max:255'],
            'assets' => ['nullable', 'string', 'max:255'],
            'asset_php' => ['nullable', 'numeric', 'min:0'],
            'asset_year' => ['nullable', 'string', 'max:255'],
            'netincome_php' => ['nullable', 'numeric', 'min:0'],
            'income_year' => ['nullable', 'string', 'max:255'],
            'function_in_the_value_chain' => ['nullable', 'string', 'max:255'],
            'production' => ['nullable', 'string', 'max:255'],
            'processing' => ['nullable', 'string', 'max:255'],
            'marketing' => ['nullable', 'string', 'max:255'],
            'engagedmilkfeeding' => ['nullable', 'string', 'max:255'],
            // Value chain units (one-time create)
            'production_units' => ['nullable', 'array'],
            'production_units.*.name' => ['required', 'string', 'max:255'],
            'production_units.*.category' => ['nullable', 'string'],
            'production_units.*.herd_type' => ['nullable', 'string'],
            'production_units.*.date_of_establishment' => ['nullable', 'date'],
            'production_units.*.contact_person' => ['nullable', 'string', 'max:255'],
            'production_units.*.position' => ['nullable', 'string', 'max:255'],
            'production_units.*.contact_number' => ['nullable', 'string', 'max:255'],
            'production_units.*.telephone_number' => ['nullable', 'string', 'max:255'],
            'production_units.*.email_address' => ['nullable', 'email', 'max:255'],
            'production_units.*.website' => ['nullable', 'string'],
            'production_units.*.number_of_staff_male' => ['nullable', 'integer', 'min:0'],
            'production_units.*.number_of_staff_female' => ['nullable', 'integer', 'min:0'],
            'production_units.*.avg_salary_of_staff' => ['nullable', 'numeric', 'min:0'],
            'consolidation_units' => ['nullable', 'array'],
            'consolidation_units.*.name' => ['required', 'string', 'max:255'],
            'consolidation_units.*.category' => ['nullable', 'string'],
            'consolidation_units.*.date_of_establishment' => ['nullable', 'date'],
            'consolidation_units.*.contact_person' => ['nullable', 'string', 'max:255'],
            'consolidation_units.*.position' => ['nullable', 'string', 'max:255'],
            'consolidation_units.*.contact_number' => ['nullable', 'string', 'max:255'],
            'consolidation_units.*.telephone_number' => ['nullable', 'string', 'max:255'],
            'consolidation_units.*.email_address' => ['nullable', 'email', 'max:255'],
            'consolidation_units.*.website' => ['nullable', 'string'],
            'consolidation_units.*.number_of_staff_male' => ['nullable', 'integer', 'min:0'],
            'consolidation_units.*.number_of_staff_female' => ['nullable', 'integer', 'min:0'],
            'consolidation_units.*.avg_salary_of_staff' => ['nullable', 'numeric', 'min:0'],
            'processing_units' => ['nullable', 'array'],
            'processing_units.*.name' => ['required', 'string', 'max:255'],
            'processing_units.*.category' => ['nullable', 'string'],
            'processing_units.*.date_of_establishment' => ['nullable', 'date'],
            'processing_units.*.contact_person' => ['nullable', 'string', 'max:255'],
            'processing_units.*.position' => ['nullable', 'string', 'max:255'],
            'processing_units.*.contact_number' => ['nullable', 'string', 'max:255'],
            'processing_units.*.telephone_number' => ['nullable', 'string', 'max:255'],
            'processing_units.*.email_address' => ['nullable', 'email', 'max:255'],
            'processing_units.*.website' => ['nullable', 'string'],
            'processing_units.*.number_of_staff_male' => ['nullable', 'integer', 'min:0'],
            'processing_units.*.number_of_staff_female' => ['nullable', 'integer', 'min:0'],
            'processing_units.*.avg_salary_of_staff' => ['nullable', 'numeric', 'min:0'],
            'marketing_units' => ['nullable', 'array'],
            'marketing_units.*.name' => ['required', 'string', 'max:255'],
            'marketing_units.*.date_of_establishment' => ['nullable', 'date'],
            'marketing_units.*.contact_person' => ['nullable', 'string', 'max:255'],
            'marketing_units.*.position' => ['nullable', 'string', 'max:255'],
            'marketing_units.*.contact_number' => ['nullable', 'string', 'max:255'],
            'marketing_units.*.telephone_number' => ['nullable', 'string', 'max:255'],
            'marketing_units.*.email_address' => ['nullable', 'email', 'max:255'],
            'marketing_units.*.website' => ['nullable', 'string'],
            'marketing_units.*.number_of_staff_male' => ['nullable', 'integer', 'min:0'],
            'marketing_units.*.number_of_staff_female' => ['nullable', 'integer', 'min:0'],
            'marketing_units.*.avg_salary_of_staff' => ['nullable', 'numeric', 'min:0'],
            // Products (one-time create)
            'products' => ['nullable', 'array'],
            'products.*.code' => ['nullable', 'string', 'max:100'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.category' => ['required', 'string', Rule::in(array_column(ProductCategoryEnum::cases(), 'value'))],
            'products.*.sub_category' => ['nullable', 'string', Rule::in(array_column(ProductSubCategoryEnum::cases(), 'value'))],
            'products.*.product_type' => ['nullable', 'string', 'max:255'],
            'products.*.size' => ['nullable', 'string', 'max:50'],
            'products.*.cost_per_qty' => ['nullable', 'numeric', 'min:0'],
            'products.*.details' => ['nullable', 'string'],
            'products.*.product_registration' => ['nullable', 'string', 'max:255'],
            'products.*.registration_file_name' => ['nullable', 'string', 'max:255'],
            'products.*.registration_file_key' => ['nullable', 'string', 'max:255'],
            'products.*.registration_file_type' => ['nullable', 'string', 'max:100', 'starts_with:application/pdf'],
            'products.*.registration_file_size' => ['nullable', 'integer', 'min:1'],
            // Documents (one-time create)
            'documents' => ['nullable', 'array'],
            'documents.*.title' => ['required', 'string', 'max:255'],
            'documents.*.file_name' => ['required', 'string', 'max:255'],
            'documents.*.file_key' => ['required', 'string', 'max:255'],
            'documents.*.file_type' => ['nullable', 'string', 'max:100'],
            'documents.*.file_size' => ['nullable', 'integer', 'min:1'],
            'documents.*.description' => ['nullable', 'string'],
            'documents.*.registration_number' => ['nullable', 'string', 'max:255'],
            'documents.*.validity' => ['nullable', 'date'],
            'documents.*.compliance_status' => ['nullable', 'string', 'in:'.implode(',', array_keys(ComplianceStatusEnum::labels()))],
            'documents.*.document_type' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'This partner code already exists.',
            'code.string' => 'Partner code must be a string.',
            'code.max' => 'Partner code may not be greater than 255 characters.',
            'zip_code.regex' => 'Zip Code Should be 4 digits.',
        ];
    }
}
