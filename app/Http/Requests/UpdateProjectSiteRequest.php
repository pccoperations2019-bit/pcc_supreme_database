<?php

namespace App\Http\Requests;

use App\Enums\ContractTypeEnum;
use App\Enums\DairyBoxEnum;
use App\Enums\EquipmentNameEnum;
use App\Enums\OwnershipTypeEnum;
use App\Enums\ValueChainStatusEnum;
use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectSiteRequest extends FormRequest
{
    use ForcesCenterForCenterScopedUser;

    public function authorize(): bool
    {
        // Ownership is checked by ProjectSitePolicy::update, which the controller
        // invokes against the bound project site.
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
            'production_partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'processing_partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'service_record_id' => ['nullable', 'integer', 'exists:service_records,id'],
            'project_site_code' => [
                'nullable', 'string', 'max:255',
                Rule::unique('project_sites', 'project_site_code')->ignore($this->route('project_site')),
            ],
            'value_chain_status' => ['nullable', 'string', Rule::in(array_keys(ValueChainStatusEnum::labels()))],
            'inauguration_date' => ['nullable', 'date'],
            'staff_name' => ['nullable', 'string', 'max:255'],
            'staff_phone' => ['nullable', 'string', 'max:255'],
            'staff_position' => ['nullable', 'string', 'max:255'],
            'type_of_contract' => ['nullable', 'string', Rule::in(array_keys(ContractTypeEnum::labels()))],
            'partner_farmer_org' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'productions' => ['nullable', 'array'],
            'productions.*.no_of_animals_to_be_delivered' => ['nullable', 'integer', 'min:0'],
            'productions.*.pcc_counterpart' => ['nullable', 'string', 'max:255'],
            'productions.*.delivered' => ['nullable', 'integer', 'min:0'],
            'productions.*.supplier' => ['nullable', 'string', 'max:255'],
            'productions.*.no_of_recipients' => ['nullable', 'integer', 'min:0'],
            'productions.*.procuring_agency' => ['nullable', 'string', 'max:255'],
            'productions.*.mode_of_procurement' => ['nullable', 'string', 'max:255'],
            'processing_infrastructure' => ['nullable', 'array'],
            'processing_infrastructure.*.type_of_ownership' => ['nullable', 'string', Rule::in(array_keys(OwnershipTypeEnum::labels()))],
            'processing_infrastructure.*.region' => ['nullable', 'string', 'max:255'],
            'processing_infrastructure.*.province' => ['nullable', 'string', 'max:255'],
            'processing_infrastructure.*.municipality' => ['nullable', 'string', 'max:255'],
            'processing_infrastructure.*.barangay' => ['nullable', 'string', 'max:255'],
            'processing_infrastructure.*.district' => ['nullable', 'string', 'max:255'],
            'processing_infrastructure.*.latitude' => ['nullable', 'numeric'],
            'processing_infrastructure.*.longitude' => ['nullable', 'numeric'],
            'processing_infrastructure.*.processing_staff' => ['nullable', 'array'],
            'processing_infrastructure.*.processing_staff.*.staff_name' => ['nullable', 'string', 'max:255'],
            'processing_infrastructure.*.processing_staff.*.contact_number' => ['nullable', 'string', 'max:255'],
            'processing_infrastructure.*.processing_staff.*.number_of_staff' => ['nullable', 'integer', 'min:0'],
            'processing_infrastructure.*.start_of_construction' => ['nullable', 'date'],
            'processing_infrastructure.*.completion_status' => ['nullable', 'string', Rule::in(array_keys(DairyBoxEnum::labels()))],
            'processing_infrastructure.*.target_date_for_completion' => ['nullable', 'date'],
            'processing_infrastructure.*.date_inaugurated' => ['nullable', 'date'],
            'processing_infrastructure.*.contractor' => ['nullable', 'string', 'max:255'],
            'equipment' => ['nullable', 'array'],
            'equipment.*.name_of_equipment' => ['nullable', 'string', Rule::in(array_keys(EquipmentNameEnum::labels()))],
            'equipment.*.quantity' => ['nullable', 'integer', 'min:0'],
            'equipment.*.capacity' => ['nullable', 'string', 'max:255'],
            'equipment.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'equipment.*.total_cost' => ['nullable', 'numeric', 'min:0'],
            'equipment.*.delivery_of_equipment' => ['nullable', 'date'],
            'equipment.*.ownership_status' => ['nullable', 'string', 'max:255'],
            'equipment.*.attachment_ownership_documents' => ['nullable', 'string', 'max:255'],
            'equipment.*.supplier_name' => ['nullable', 'string', 'max:255'],
            'equipment.*.date_installed' => ['nullable', 'date'],
            'processing_staff' => ['nullable', 'array'],
            'processing_staff.*.staff_name' => ['nullable', 'string', 'max:255'],
            'processing_staff.*.contact_number' => ['nullable', 'string', 'max:255'],
            'processing_staff.*.number_of_staff' => ['nullable', 'integer', 'min:0'],
            'marketing_staff' => ['nullable', 'array'],
            'marketing_staff.*.staff_name' => ['nullable', 'string', 'max:255'],
            'marketing_staff.*.contact_number' => ['nullable', 'string', 'max:255'],
            'marketing_staff.*.number_of_staff' => ['nullable', 'integer', 'min:0'],
            'marketing_infrastructure' => ['nullable', 'array'],
            'marketing_infrastructure.*.update_dairy_box_address' => ['nullable', 'boolean'],
            'marketing_infrastructure.*.dairy_box_id' => ['nullable', 'integer', 'exists:dairy_boxes,id'],
            'marketing_infrastructure.*.marketing_staff' => ['nullable', 'array'],
            'marketing_infrastructure.*.marketing_staff.*.staff_name' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.marketing_staff.*.contact_number' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.marketing_staff.*.number_of_staff' => ['nullable', 'integer', 'min:0'],
            'marketing_infrastructure.*.region' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.province' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.municipality' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.barangay' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.district' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.partner' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.business_name' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.manager' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.latitude' => ['nullable', 'numeric'],
            'marketing_infrastructure.*.longitude' => ['nullable', 'numeric'],
            'marketing_infrastructure.*.operational_status' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.launched_date' => ['nullable', 'date'],
            'marketing_infrastructure.*.start_of_construction' => ['nullable', 'date'],
            'marketing_infrastructure.*.date_of_completion' => ['nullable', 'date'],
            'marketing_infrastructure.*.contractor' => ['nullable', 'string', 'max:255'],
            'marketing_infrastructure.*.date_inaugurated' => ['nullable', 'date'],
            'training_records' => ['nullable', 'array'],
            'training_records.*.project_orientation_date' => ['nullable', 'date'],
            'training_records.*.project_orientation_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.strategic_planning_date' => ['nullable', 'date'],
            'training_records.*.strategic_planning_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.social_preparation_training_date' => ['nullable', 'date'],
            'training_records.*.social_preparation_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.forage_production_training_date' => ['nullable', 'date'],
            'training_records.*.forage_production_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.silage_making_training_date' => ['nullable', 'date'],
            'training_records.*.silage_making_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.animal_health_training_date' => ['nullable', 'date'],
            'training_records.*.animal_health_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.breeding_management_training_date' => ['nullable', 'date'],
            'training_records.*.breeding_management_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.milk_handling_training_date' => ['nullable', 'date'],
            'training_records.*.milk_handling_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.milk_processing_training_date' => ['nullable', 'date'],
            'training_records.*.milk_processing_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.animal_husbandry_training_date' => ['nullable', 'date'],
            'training_records.*.animal_husbandry_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.organic_fertilizer_training_date' => ['nullable', 'date'],
            'training_records.*.organic_fertilizer_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.cooperative_management_training_date' => ['nullable', 'date'],
            'training_records.*.cooperative_management_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.fls_launching_date' => ['nullable', 'date'],
            'training_records.*.fls_launching_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.fls_buffalo_training_date' => ['nullable', 'date'],
            'training_records.*.fls_buffalo_training_manual_pax' => ['nullable', 'integer', 'min:0'],
            'training_records.*.fls_date' => ['nullable', 'date'],
            'training_records.*.fls_manual_pax' => ['nullable', 'integer', 'min:0'],
            'budget_utilization' => ['nullable', 'array'],
            'budget_utilization.budget_allocated' => ['nullable', 'numeric', 'min:0'],
            'budget_utilization.obligated' => ['nullable', 'numeric', 'min:0'],
            'budget_utilization.disbursed' => ['nullable', 'numeric', 'min:0'],
            'budget_utilization.utilization_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'legal_documents' => ['nullable', 'array'],
            'legal_documents.moa' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'legal_documents.dod_usufruct' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'legal_documents.management_agreement_contract' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'legal_documents.dairy_buffalo_dispersal_agreement' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'center_id.exists' => 'Selected center does not exist.',
            'production_partner_id.exists' => 'Selected partner does not exist.',
            'project_id.exists' => 'Selected project does not exist.',
            'project_site_code.unique' => 'This project site code is already taken.',
        ];
    }
}
