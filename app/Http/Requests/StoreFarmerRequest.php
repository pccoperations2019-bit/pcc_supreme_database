<?php

namespace App\Http\Requests;

use App\Enums\CivilStatusEnum;
use App\Enums\ComplianceStatusEnum;
use App\Enums\EducationalAttainmentEnum;
use App\Enums\EntrepreneurTypeEnum;
use App\Enums\FarmRegistrationStatusEnum;
use App\Enums\FeedingManagementEnum;
use App\Enums\IncomeSourceEnum;
use App\Enums\ManagementSystemEnum;
use App\Enums\MembershipTypeEnum;
use App\Enums\TvTypeEnum;
use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmerRequest extends FormRequest
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

        $partnerId = $this->input('partner_id');
        if (in_array($partnerId, ['', null, 'null'], true)) {
            $this->merge(['partner_id' => null]);
        }

        $additionalDetails = $this->input('additional_details');
        if (is_string($additionalDetails)) {
            $decoded = json_decode($additionalDetails, true);
            if (is_array($decoded)) {
                $this->merge(['additional_details' => $decoded]);
            }
        }

        $statusOfRegistration = $this->input('status_of_registration');
        if (! empty($statusOfRegistration)) {
            $normalizedStatus = FarmRegistrationStatusEnum::tryFrom($statusOfRegistration)
                ?? FarmRegistrationStatusEnum::tryFroMatches((string) $statusOfRegistration);
            $this->merge([
                'status_of_registration' => $normalizedStatus?->value ?? $statusOfRegistration,
            ]);
        }

        $this->merge(['project_category' => $this->normalizeProjectCategories($this->input('project_category'))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $partnerIdRule = ['nullable', 'integer'];
        if ($this->input('partner_id') !== null && $this->input('partner_id') !== '') {
            $partnerIdRule[] = 'exists:partners,id';
        }

        return [
            'center_id' => ['required', 'integer', 'exists:centers,id'],
            'partner_id' => $partnerIdRule,
            'code' => ['required', 'string', 'max:255', 'unique:farmers,code'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],

            // Personal Information
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'extension_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            'date_of_interview' => ['nullable', 'date'],
            'birthday' => ['nullable', 'date'],
            'civil_status' => ['nullable', 'string', Rule::in(array_keys(CivilStatusEnum::labels()))],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'household_size' => ['nullable', 'integer', 'min:0'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'date_of_enrollment' => ['nullable', 'date'],
            'operation_in_charge' => ['nullable', 'string', 'max:255'],
            'is_assisted' => ['nullable', 'boolean'],
            'date_assisted' => ['nullable', 'date'],
            'project_category' => ['nullable', 'array'],
            'project_category.*' => ['string', 'max:255'],
            'has_personal_cellphone' => ['nullable', 'boolean'],
            'purok_zone' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'regex:/^\d{4}$/'],

            // Address fields
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],

            // Photo upload
            'photo' => ['nullable', 'image', 'max:2048'],

            // Farming Information
            'management_system' => ['nullable', 'string', Rule::in(array_keys(ManagementSystemEnum::labels()))],
            'is_drylot' => ['nullable', 'boolean'],
            'duration_of_stocking' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_applied_in_the_field' => ['nullable', 'boolean'],
            'is_dry_rice_field' => ['nullable', 'boolean'],
            'is_dry_vegetable' => ['nullable', 'boolean'],

            // Livestock Information
            'dairy_cattle' => ['nullable', 'integer', 'min:0'],
            'beef_cattle' => ['nullable', 'integer', 'min:0'],
            'chicken' => ['nullable', 'integer', 'min:0'],
            'duck' => ['nullable', 'integer', 'min:0'],
            'geese' => ['nullable', 'integer', 'min:0'],
            'goat' => ['nullable', 'integer', 'min:0'],
            'swine' => ['nullable', 'integer', 'min:0'],
            'horse' => ['nullable', 'integer', 'min:0'],
            'other_livestock' => ['nullable', 'integer', 'min:0'],

            // Income Information
            'main_source_of_income' => ['nullable', 'string', Rule::in(array_keys(IncomeSourceEnum::labels()))],
            'main_annual_net_income' => ['nullable', 'numeric', 'min:0'],
            'secondary_source_of_income' => ['nullable', 'string', Rule::in(array_keys(IncomeSourceEnum::labels()))],
            'secondary_annual_net_income' => ['nullable', 'numeric', 'min:0'],
            'tertiary_source_of_income' => ['nullable', 'string', Rule::in(array_keys(IncomeSourceEnum::labels()))],
            'tertiary_annual_net_income' => ['nullable', 'numeric', 'min:0'],
            'quaternary_source_of_income' => ['nullable', 'string', Rule::in(array_keys(IncomeSourceEnum::labels()))],
            'quaternary_annual_net_income' => ['nullable', 'numeric', 'min:0'],
            'avg_net_income' => ['nullable', 'numeric', 'min:0'],
            'staff_count_male' => ['nullable', 'integer', 'min:0'],
            'staff_count_female' => ['nullable', 'integer', 'min:0', 'lte:staff_count_male'],

            // Sources of Income (repeating table, one row per household member)
            'source_of_income_details' => ['nullable', 'array'],
            'source_of_income_details.*.household_member' => ['nullable', 'string', 'max:255'],
            'source_of_income_details.*.source_of_income' => ['nullable', 'string', 'max:255'],
            'source_of_income_details.*.gross_income' => ['nullable', 'numeric', 'min:0'],
            'source_of_income_details.*.net_income' => ['nullable', 'numeric', 'min:0'],
            'source_of_income_details.*.frequency_per_year' => ['nullable', 'integer', 'min:0'],

            // Management Information
            'is_vermicomposting' => ['nullable', 'boolean'],
            'is_pasture_range_paddock' => ['nullable', 'boolean'],
            'is_daily_spread' => ['nullable', 'boolean'],
            'is_storage_bulking' => ['nullable', 'boolean'],
            'is_liquid_slurry' => ['nullable', 'boolean'],
            'is_anaerobic_digester' => ['nullable', 'boolean'],

            // SECTION 17 - Twice-a-day Milking
            'is_twice_a_day_milking' => ['nullable', 'boolean'],

            // SECTION 18 - Daily Milk Production (liters)
            'daily_milk_production_min' => ['nullable', 'numeric', 'min:0'],
            'daily_milk_production_max' => ['nullable', 'numeric', 'min:0'],

            // SECTION 19 - Milk Price per Liter
            'milk_price_per_liter_min' => ['nullable', 'numeric', 'min:0'],
            'milk_price_per_liter_max' => ['nullable', 'numeric', 'min:0'],

            // SECTION 20 - Where Do You Sell Your Milk Produce?
            'milk_selling_locations' => ['nullable', 'array'],
            'milk_selling_locations.*' => ['string'],
            'milk_selling_restaurant_specify' => ['nullable', 'string', 'max:255'],
            'milk_selling_processor_specify' => ['nullable', 'string', 'max:255'],
            'milk_selling_collector_name' => ['nullable', 'string', 'max:255'],
            'milk_selling_others_specify' => ['nullable', 'string', 'max:255'],

            // SECTION 23 - Assistance Needed from Government
            'assistance_needed' => ['nullable', 'array', 'max:4'],
            'assistance_needed.*' => ['nullable', 'string', 'max:5000'],
            'staff_count' => ['nullable', 'integer', 'min:0'],
            'gps_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pcc_assets_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pcc_assets_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ave_monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99'],
            'status_google_map' => ['nullable', 'string', 'max:255'],
            'google_map_link' => ['nullable', 'string', 'max:2048'],
            'geotag_image' => ['nullable', 'string', 'max:2048'],
            'geotag_image_file' => ['nullable', 'image', 'max:4096'],
            'entrepreneur_image' => ['nullable', 'string', 'max:2048'],
            'entrepreneur_image_file' => ['nullable', 'image', 'max:4096'],
            'enterprise_image' => ['nullable', 'string', 'max:2048'],
            'enterprise_image_file' => ['nullable', 'image', 'max:4096'],

            // Extra fields captured from uploads/imports (stored in farmers.metadata)
            'additional_details' => ['nullable', 'array'],
            'additional_details.*' => ['nullable'],

            // Farm Information
            'farm_name' => ['nullable', 'string', 'max:255'],
            'farm_region' => ['nullable', 'string', 'max:255'],
            'farm_province' => ['nullable', 'string', 'max:255'],
            'farm_municipality_city' => ['nullable', 'string', 'max:255'],
            'farm_barangay' => ['nullable', 'string', 'max:255'],
            'farm_purok_zone' => ['nullable', 'string', 'max:255'],
            'farm_district' => ['nullable', 'string', 'max:255'],
            'farm_street' => ['nullable', 'string', 'max:255'],
            'proposed_farm_name' => ['nullable', 'string', 'max:255'],
            'type_of_farm_business_registration' => ['nullable', 'array'],
            'type_of_farm_business_registration.*' => ['string'],
            'status_of_registration' => ['nullable', Rule::enum(FarmRegistrationStatusEnum::class)],
            'farm_registration_number' => ['nullable', 'string', 'max:255'],
            'has_rsbsa' => ['nullable', 'boolean'],
            'year_farm_established' => ['nullable', 'integer', 'digits:4', 'min:1900', 'max:2100'],
            'pcc_projects_assets' => ['nullable', 'string', 'max:255'],

            // Manure Management - additional methods
            'is_solid_storage_covered' => ['nullable', 'boolean'],
            'is_solid_storage_additives' => ['nullable', 'boolean'],
            'is_uncovered_anaerobic_lagoon' => ['nullable', 'boolean'],

            // Other Livestock
            'other_livestock_label' => ['nullable', 'string', 'max:255'],
            'other_livestock_count' => ['nullable', 'integer', 'min:0'],
            'other_livestock_details' => ['nullable', 'array'],
            'other_livestock_details.*.label' => ['nullable', 'string', 'max:255'],
            'other_livestock_details.*.count' => ['nullable', 'integer', 'min:0'],

            // SECTION 12 - Animal Inventory (fixed repeating table)
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

            // SECTION 12 sub-section - Number of Animals According to Ownership
            'animal_ownership' => ['nullable', 'array'],
            'animal_ownership.pcc_owned' => ['nullable', 'array'],
            'animal_ownership.pcc_owned.checked' => ['nullable', 'boolean'],
            'animal_ownership.pcc_owned.count' => ['nullable', 'integer', 'min:0'],
            'animal_ownership.lgu_owned' => ['nullable', 'array'],
            'animal_ownership.lgu_owned.checked' => ['nullable', 'boolean'],
            'animal_ownership.lgu_owned.count' => ['nullable', 'integer', 'min:0'],
            'animal_ownership.coop_owned' => ['nullable', 'array'],
            'animal_ownership.coop_owned.checked' => ['nullable', 'boolean'],
            'animal_ownership.coop_owned.count' => ['nullable', 'integer', 'min:0'],
            'animal_ownership.palwi' => ['nullable', 'array'],
            'animal_ownership.palwi.checked' => ['nullable', 'boolean'],
            'animal_ownership.palwi.count' => ['nullable', 'integer', 'min:0'],
            'animal_ownership.farmer_owned' => ['nullable', 'array'],
            'animal_ownership.farmer_owned.checked' => ['nullable', 'boolean'],
            'animal_ownership.farmer_owned.count' => ['nullable', 'integer', 'min:0'],
            'animal_ownership.others' => ['nullable', 'array'],
            'animal_ownership.others.checked' => ['nullable', 'boolean'],
            'animal_ownership.others.count' => ['nullable', 'integer', 'min:0'],
            'animal_ownership.others.specify' => ['nullable', 'string', 'max:255'],
            'animal_ownership.share_50_50' => ['nullable', 'array'],
            'animal_ownership.share_50_50.checked' => ['nullable', 'boolean'],
            'animal_ownership.share_50_50.count' => ['nullable', 'integer', 'min:0'],

            // SECTION 14 - Feeding Management
            'feeding_management' => ['nullable', 'string', Rule::enum(FeedingManagementEnum::class)],

            // Socio-Economic Information
            'position' => ['nullable', 'string', 'max:255'],
            'year_involvement_in_business' => ['nullable', 'integer', 'min:0'],
            'family_member_attending_school_count' => ['nullable', 'integer', 'min:0'],
            'female_count' => ['nullable', 'integer', 'min:0'],
            'male_count' => ['nullable', 'integer', 'min:0'],
            'is_pwd' => ['nullable', 'boolean'],
            'is_4ps' => ['nullable', 'boolean'],
            'is_ips' => ['nullable', 'boolean'],
            'is_indigent' => ['nullable', 'boolean'],
            'indigenous_group' => ['nullable', 'string', 'max:255'],
            'membership_in_organization' => ['nullable', 'string', Rule::in(array_keys(MembershipTypeEnum::labels()))],
            'name_of_organization' => ['nullable', 'string', 'max:255'],
            'tv_type' => ['nullable', 'string', Rule::in(array_keys(TvTypeEnum::labels()))],
            'educational_attainment' => ['nullable', 'string', Rule::in(array_keys(EducationalAttainmentEnum::labels()))],
            'ndalto_certification_status' => ['nullable', 'string', 'max:255'],
            'ndalto_certification' => ['nullable', 'string', 'max:255'],
            'type_of_entrepreneur' => ['nullable', 'string', Rule::in(array_keys(EntrepreneurTypeEnum::labels()))],

            // RSBSA & Production Information
            'rsbsa_cert_status' => ['nullable', 'string', 'max:255'],
            'rsbsa_certificate_of_registration' => ['nullable', 'string', 'max:255'],
            'if_producer_please_specify' => ['nullable', 'string', 'max:255'],
            'involved_in_buffalo_farming' => ['nullable', 'boolean'],
            'how_many_in_family_are_involved_in_buffalo_farming' => ['nullable', 'integer', 'min:0'],
            'are_you_the_head_of_the_family' => ['nullable', 'boolean'],
            'if_no_highest_educational_attainment_of_head_of_family' => ['nullable', 'string', 'max:255'],
            'challenges_or_problems_encountered_in_dairy_farming' => ['nullable', 'string', 'max:5000'],

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
     * Normalize project_category into a list of values.
     *
     * Accepts legacy single strings ("cbed"), comma-separated strings,
     * JSON arrays, or arrays — always returns null or a clean list.
     */
    protected function normalizeProjectCategories(mixed $value): ?array
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                $value = is_array($decoded) ? $decoded : [$trimmed];
            } else {
                $value = str_contains($trimmed, ',') ? explode(',', $trimmed) : [$trimmed];
            }
        }

        if (! is_array($value)) {
            return null;
        }

        $list = array_values(array_unique(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $value
        ), fn ($item) => $item !== '')));

        return $list === [] ? null : $list;
    }

    /**
     * Get the custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'This farmer code already exists.',
            'code.string' => 'Farmer code must be a string.',
            'code.max' => 'Farmer code may not be greater than 255 characters.',
            'center_id.required' => 'Center is required.',
            'center_id.exists' => 'The selected center is invalid.',
            'partner_id.exists' => 'The selected partner is invalid.',
            'partner_id.integer' => 'Partner must be a valid ID.',
            'first_name.required' => 'First name is required.',
            'first_name.max' => 'First name may not be greater than 255 characters.',
            'last_name.required' => 'Last name is required.',
            'last_name.max' => 'Last name may not be greater than 255 characters.',
            'extension_name.max' => 'Extension name may not be greater than 255 characters.',
            'gender.in' => 'Gender must be Male, Female, or Other.',
            'date_of_interview.date' => 'Date of interview must be a valid date.',
            'civil_status.in' => 'Civil status is invalid.',
            'spouse_name.max' => 'Spouse name may not be greater than 255 characters.',
            'household_size.integer' => 'Household size must be an integer.',
            'household_size.min' => 'Household size must be at least 0.',
            'status.in' => 'Status must be active or inactive.',
            'zip_code.regex' => 'Zip Code Should be 4 digits.',
            'birthday.date' => 'Birthday must be a valid date.',
            'date_of_enrollment.date' => 'Date of enrollment must be a valid date.',

            'photo.image' => 'Photo must be an image.',
            'photo.max' => 'Photo may not be greater than 2MB.',
            'management_system.in' => 'Management system is invalid.',
            'is_drylot.boolean' => 'Is drylot must be true or false.',
            'duration_of_stocking.integer' => 'Duration of stocking must be a whole number.',
            'duration_of_stocking.min' => 'Duration of stocking must be at least 0.',
            'duration_of_stocking.max' => 'Duration of stocking may not be greater than 65535.',
            'is_applied_in_the_field.boolean' => 'Is applied in the field must be true or false.',
            'is_dry_rice_field.boolean' => 'Is dry rice field must be true or false.',
            'is_dry_vegetable.boolean' => 'Is dry vegetable must be true or false.',
            'dairy_cattle.integer' => 'Dairy cattle count must be an integer.',
            'dairy_cattle.min' => 'Dairy cattle count must be at least 0.',
            'beef_cattle.integer' => 'Beef cattle count must be an integer.',
            'beef_cattle.min' => 'Beef cattle count must be at least 0.',
            'chicken.integer' => 'Chicken count must be an integer.',
            'chicken.min' => 'Chicken count must be at least 0.',
            'duck.integer' => 'Duck count must be an integer.',
            'duck.min' => 'Duck count must be at least 0.',
            'geese.integer' => 'Geese count must be an integer.',
            'geese.min' => 'Geese count must be at least 0.',
            'goat.integer' => 'Goat count must be an integer.',
            'goat.min' => 'Goat count must be at least 0.',
            'swine.integer' => 'Swine count must be an integer.',
            'swine.min' => 'Swine count must be at least 0.',
            'horse.integer' => 'Horse count must be an integer.',
            'horse.min' => 'Horse count must be at least 0.',
            'other_livestock.integer' => 'Other livestock count must be an integer.',
            'other_livestock.min' => 'Other livestock count must be at least 0.',
            'main_source_of_income.in' => 'Main source of income is invalid.',
            'main_annual_net_income.numeric' => 'Main annual net income must be a number.',
            'main_annual_net_income.min' => 'Main annual net income must be at least 0.',
            'secondary_source_of_income.in' => 'Secondary source of income is invalid.',
            'secondary_annual_net_income.numeric' => 'Secondary annual net income must be a number.',
            'secondary_annual_net_income.min' => 'Secondary annual net income must be at least 0.',
            'tertiary_source_of_income.in' => 'Tertiary source of income is invalid.',
            'tertiary_annual_net_income.numeric' => 'Tertiary annual net income must be a number.',
            'tertiary_annual_net_income.min' => 'Tertiary annual net income must be at least 0.',
            'quaternary_source_of_income.in' => 'Quaternary source of income is invalid.',
            'quaternary_annual_net_income.numeric' => 'Quaternary annual net income must be a number.',
            'quaternary_annual_net_income.min' => 'Quaternary annual net income must be at least 0.',
            'avg_net_income.numeric' => 'Average net income must be a number.',
            'avg_net_income.min' => 'Average net income must be at least 0.',
            'ave_monthly_salary.numeric' => 'Average monthly salary must be a number.',
            'ave_monthly_salary.min' => 'Average monthly salary must be at least 0.',
            'ave_monthly_salary.max' => 'Average monthly salary exceeds the maximum allowed value.',
            'total_net_income.numeric' => 'Total net income must be a number.',
            'total_net_income.min' => 'Total net income must be at least 0.',
            'staff_count_male.integer' => 'Staff count (male) must be an integer.',
            'staff_count_male.min' => 'Staff count (male) must be at least 0.',
            'staff_count.integer' => 'Staff count must be an integer.',
            'staff_count.min' => 'Staff count must be at least 0.',
            'staff_count_female.integer' => 'Staff count (female) must be an integer.',
            'staff_count_female.min' => 'Staff count (female) must be at least 0.',
            'is_vermicomposting.boolean' => 'Is vermicomposting must be true or false.',
            'is_daily_spread.boolean' => 'Is daily spread must be true or false.',
            'is_storage_bulking.boolean' => 'Is storage bulking must be true or false.',
            'is_liquid_slurry.boolean' => 'Is liquid slurry must be true or false.',
            'is_anaerobic_digester.boolean' => 'Is anaerobic digester must be true or false.',
            'gps_latitude.numeric' => 'GPS latitude must be a number.',
            'gps_latitude.between' => 'GPS latitude must be between -90 and 90.',
            'gps_longitude.numeric' => 'GPS longitude must be a number.',
            'gps_longitude.between' => 'GPS longitude must be between -180 and 180.',
            'status_google_map.max' => 'Status Google Map may not be greater than 255 characters.',
            'google_map_link.max' => 'Google Map link may not be greater than 2048 characters.',
            'geotag_image.max' => 'Geotag image may not be greater than 2048 characters.',
            'geotag_image_file.image' => 'Geotag image file must be an image.',
            'geotag_image_file.max' => 'Geotag image file may not be greater than 4MB.',
            'entrepreneur_image.max' => 'Entrepreneur image may not be greater than 2048 characters.',
            'entrepreneur_image_file.image' => 'Entrepreneur image file must be an image.',
            'entrepreneur_image_file.max' => 'Entrepreneur image file may not be greater than 4MB.',
            'enterprise_image.max' => 'Enterprise image may not be greater than 2048 characters.',
            'enterprise_image_file.image' => 'Enterprise image file must be an image.',
            'enterprise_image_file.max' => 'Enterprise image file may not be greater than 4MB.',
            'position.max' => 'Position may not be greater than 255 characters.',
            'year_involvement_in_business.integer' => 'Year involvement in business must be an integer.',
            'year_involvement_in_business.min' => 'Year involvement in business must be at least 0.',
            'family_member_attending_school_count.integer' => 'Family member attending school count must be an integer.',
            'family_member_attending_school_count.min' => 'Family member attending school count must be at least 0.',
            'female_count.integer' => 'Female count must be an integer.',
            'female_count.min' => 'Female count must be at least 0.',
            'male_count.integer' => 'Male count must be an integer.',
            'male_count.min' => 'Male count must be at least 0.',
            'is_pwd.boolean' => 'Is PWD must be true or false.',
            'is_4ps.boolean' => 'Is 4Ps must be true or false.',
            'is_ips.boolean' => 'Is IPS must be true or false.',
            'indigenous_group.max' => 'Indigenous group may not be greater than 255 characters.',
            'educational_attainment.in' => 'Educational attainment is invalid.',
            'ndalto_certification_status.max' => 'NDALTO certification status may not be greater than 255 characters.',
            'ndalto_certification.max' => 'NDALTO certification may not be greater than 255 characters.',
            'type_of_entrepreneur.in' => 'Type of entrepreneur is invalid.',
            'rsbsa_cert_status.max' => 'RSBSA certification status may not be greater than 255 characters.',
            'rsbsa_certificate_of_registration.max' => 'RSBSA certificate of registration may not be greater than 255 characters.',
            'if_producer_please_specify.max' => 'Producer specification may not be greater than 255 characters.',
            'involved_in_buffalo_farming.boolean' => 'Involved in buffalo farming must be true or false.',
            'how_many_in_family_are_involved_in_buffalo_farming.integer' => 'Family members involved in buffalo farming must be an integer.',
            'how_many_in_family_are_involved_in_buffalo_farming.min' => 'Family members involved in buffalo farming must be at least 0.',
            'are_you_the_head_of_the_family.boolean' => 'Head of family must be true or false.',
            'if_no_highest_educational_attainment_of_head_of_family.max' => 'Head of family educational attainment may not be greater than 255 characters.',
            'challenges_or_problems_encountered_in_dairy_farming.max' => 'Challenges may not be greater than 5000 characters.',
        ];
    }
}
