<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'sub_category' => ['nullable', 'string', 'max:50'],
            'product_type' => ['nullable', 'string', 'max:100'],
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
