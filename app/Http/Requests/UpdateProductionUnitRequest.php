<?php

namespace App\Http\Requests;

use App\Enums\HerdTypeEnum;
use App\Enums\ProductionCategoryEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->category) {
            $this->merge([
                'category' => ProductionCategoryEnum::tryFrom($this->category)?->value,
            ]);
        }
        if ($this->herd_type) {
            $this->merge([
                'herd_type' => HerdTypeEnum::tryFrom($this->herd_type)?->value,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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
