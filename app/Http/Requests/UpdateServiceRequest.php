<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
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
     * @return array<string, Rule|array<mixed>>
     */
    public function rules(): array
    {
        return [
            'simplified_code' => ['nullable', 'string', 'max:32'],
            'mfo_code' => ['nullable', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:32'],
            'value_chain_segment' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'array'],
            'category.*' => ['string', 'max:255'],
            'sub_category' => ['nullable', 'array'],
            'sub_category.*' => ['string', 'max:255'],
            'type' => ['nullable', 'array'],
            'type.*' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Service name is required.',
            'name.max' => 'Service name may not be greater than 255 characters.',
            'simplified_code.max' => 'Simplified code may not be greater than 32 characters.',
            'mfo_code.max' => 'MFO code may not be greater than 32 characters.',
            'program.max' => 'Program may not be greater than 32 characters.',
            'value_chain_segment.max' => 'Value chain segment may not be greater than 64 characters.',
            'category.*.max' => 'Each category may not be greater than 255 characters.',
            'sub_category.*.max' => 'Each sub category may not be greater than 255 characters.',
            'type.*.max' => 'Each service type may not be greater than 255 characters.',
        ];
    }
}
