<?php

namespace App\Http\Requests;

use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
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
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'code' => ['nullable', 'string', 'max:255', 'unique:partners,code,'.$this->route('partner')->id],
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
