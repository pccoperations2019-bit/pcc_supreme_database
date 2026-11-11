<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

class StoreEpahpGatasangKalabawRequest extends FormRequest
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
                Rule::unique('epahp_gatasang_kalabaw')
                    ->where('year', $this->input('year'))
                    ->ignore($this->route('epahpGatasangKalabaw')),
            ],
            'year' => ['required', 'integer', 'min:1900', 'max:'.$currentYear],
            'number_of_cooperatives' => ['required', 'integer', 'min:0'],
            'number_of_farmer_recipients' => ['required', 'integer', 'min:0'],
            'number_of_animals_entrusted' => ['required', 'integer', 'min:0'],
            'dswd_counterpart' => ['nullable', 'numeric', 'min:0'],
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
