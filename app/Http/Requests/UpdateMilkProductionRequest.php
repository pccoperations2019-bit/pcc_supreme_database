<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMilkProductionRequest extends FormRequest
{
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
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'program' => ['required', 'string', 'in:ih,cbed'],
            'production_code' => ['nullable', 'string', 'max:32'],
            'id_number' => ['nullable', 'string', 'max:255'],
            'collection_date' => ['nullable', 'date'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'farmer_id' => ['nullable', 'integer', 'exists:farmers,id'],
            'herd_id' => ['nullable', 'integer', 'exists:herds,id'],
            'transaction_date' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
            'am_volume' => ['nullable', 'numeric'],
            'pm_volume' => ['nullable', 'numeric'],
            'total_volume_produced' => ['nullable', 'numeric'],
            'volume_from_farmers' => ['nullable', 'numeric', 'min:0'],
            'milking_times' => ['nullable', 'string', 'max:50'],
            'source_of_milk' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'week' => ['nullable', 'integer', 'min:1', 'max:53'],
            'number_of_lactating' => ['nullable', 'integer', 'min:0'],
            'avg_milk_per_animal_per_day' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'price_per_kg' => ['nullable', 'numeric', 'min:0'],
            'milk_fed_to_calves' => ['nullable', 'numeric', 'min:0'],
            'family_milk_consumption' => ['nullable', 'numeric', 'min:0'],
            'milk_spoiled' => ['nullable', 'numeric', 'min:0'],
            'milk_traded' => ['nullable', 'numeric', 'min:0'],
            'sales_of_value_added_milk' => ['nullable', 'numeric', 'min:0'],
            'value_of_total_milk_prodn_purchased' => ['nullable', 'numeric', 'min:0'],
            'total_milk_prodn_purchased' => ['nullable', 'numeric', 'min:0'],
            'source_of_milk_purchased' => ['nullable', 'string', 'max:255'],
            'f1' => ['nullable', 'numeric'],
            'f2' => ['nullable', 'numeric'],
        ];
    }
}
