<?php

namespace App\Http\Requests;

use App\Enums\HerdTypeEnum;
use App\Enums\ProductionCategoryEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductionUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => $this->category && $this->category !== ''
                ? ProductionCategoryEnum::tryFrom($this->category)?->value
                : ProductionCategoryEnum::DEFAULT,
            'herd_type' => $this->herd_type && $this->herd_type !== ''
                ? HerdTypeEnum::tryFrom($this->herd_type)?->value
                : HerdTypeEnum::DEFAULT,
            'number_of_staff_female' => $this->number_of_staff_female ?? 0,
            'number_of_staff_male' => $this->number_of_staff_male ?? 0,
            'avg_salary_of_staff' => $this->avg_salary_of_staff ?? 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string'],
            'herd_type' => ['nullable', 'string'],
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
