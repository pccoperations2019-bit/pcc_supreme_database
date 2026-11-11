<?php

namespace App\Http\Requests;

use App\Enums\ComplianceStatusEnum;
use App\Enums\ProductCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Creating a center also provisions its center-admin account, so it is a
        // management action. Without this, authorize() returned true and any
        // signed-in account — including an encoder — could mint a center (and a
        // center-admin for it) for a center of its choosing.
        return $this->user()?->can('manage-center') ?? false;
    }

    /**
     * Whether the username was derived from the center code rather than supplied.
     */
    private bool $usernameDerived = false;

    /**
     * Username is optional on the form; fall back to the lowercased center code
     * so the account always gets a valid, unique users.username.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('username')) && filled($this->input('code'))) {
            $this->merge(['username' => Str::slug($this->input('code'))]);
            $this->usernameDerived = true;
        }
    }

    public function rules(): array
    {
        return [
            // centers.code is char(12); without this the DB throws a 500 instead of
            // returning a validation error.
            'code' => ['required', 'string', 'max:12', 'unique:centers,code'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProfileStatusEnum::labels()))],
            'website' => ['nullable', 'string'],
            'date_of_establishment' => ['nullable', 'date'],
            'date_assisted' => ['nullable', 'date'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProgramEnum::labels()))],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'telephone_number' => ['nullable', 'string', 'max:255'],
            'email_address' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Center account login credentials (username is optional; see prepareForValidation)
            'username' => ['required', 'string', 'min:3', 'max:255', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:10'],
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
            'products.*.product_registration' => ['nullable', 'string', 'max:255'],
            'products.*.size' => ['nullable', 'string', 'max:50'],
            'products.*.cost_per_qty' => ['nullable', 'numeric', 'min:0'],
            'products.*.details' => ['nullable', 'string'],
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
            'documents.*.document_type' => ['nullable', 'string', 'max:255'],
            'documents.*.compliance_status' => ['nullable', 'string', 'in:'.implode(',', array_keys(ComplianceStatusEnum::labels()))],
        ];
    }

    /**
     * Get the custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        // When the username was derived from the center code rather than typed,
        // point the user at the field they can actually edit.
        $derivedHint = 'The center code cannot be used as a username automatically. Please enter a username.';

        return [
            'code.unique' => 'This center code already exists.',
            'code.string' => 'Center code must be a string.',
            'code.max' => 'Center code may not be greater than 12 characters.',
            'email_address.unique' => 'This email address is already in use.',
            'email_address.required' => 'Email address is required.',
            'username.unique' => $this->usernameDerived ? $derivedHint : 'This username is already taken.',
            'username.min' => $this->usernameDerived ? $derivedHint : 'Username must be at least 3 characters.',
            'username.alpha_dash' => $this->usernameDerived ? $derivedHint : 'Username may only contain letters, numbers, dashes and underscores.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}
