<?php

namespace App\Http\Requests;

use App\Enums\ProductCategoryEnum;
use App\Enums\ProductSubCategoryEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_column(ProductCategoryEnum::cases(), 'value'))],
            'sub_category' => ['nullable', 'string', Rule::in(array_column(ProductSubCategoryEnum::cases(), 'value'))],
            'product_type' => ['required', 'string', 'max:100'],
            'product_registration' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
            'cost_per_qty' => ['nullable', 'numeric', 'min:0'],
            'details' => ['nullable', 'string'],
            'registration_file_name' => ['nullable', 'string', 'max:255'],
            'registration_file_key' => ['nullable', 'string', 'max:255', 'required_with:registration_file_name,registration_file_type,registration_file_size'],
            'registration_file_type' => ['nullable', 'string', 'max:100', 'starts_with:application/pdf'],
            'registration_file_size' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
