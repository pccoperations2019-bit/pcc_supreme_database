<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_establishment' => ['nullable', 'date'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'telephone_number' => ['nullable', 'string', 'max:255'],
            'email_address' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string'],
            'number_of_staff_male' => ['nullable', 'integer', 'min:0'],
            'number_of_staff_female' => ['nullable', 'integer', 'min:0'],
            'avg_salary_of_staff' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
