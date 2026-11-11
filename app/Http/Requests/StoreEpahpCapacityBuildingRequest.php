<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

class StoreEpahpCapacityBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentYear = Date::now()->year;

        return [
            'center_id' => [
                'required',
                'integer',
                'exists:centers,id',
                Rule::unique('epahp_capacity_building')
                    ->where('year', $this->input('year'))
                    ->ignore($this->route('epahpCapacityBuilding')),
            ],
            'year' => ['required', 'integer', 'min:1900', 'max:'.$currentYear],
            'number_of_cooperatives' => ['required', 'integer', 'min:0'],
            'number_of_farmers_trained' => ['required', 'integer', 'min:0'],
            'training_conducted' => ['required', 'integer', 'min:0'],
            'pcc_budget_allocated' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'center_id.unique' => 'A record for this center and year already exists.',
            'year.min' => 'The year must be at least 1900.',
            'year.max' => 'The year must not be in the future.',
        ];
    }

    public function attributes(): array
    {
        return [
            'center_id' => 'center',
        ];
    }
}
