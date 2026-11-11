<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'program' => ['required', 'string', 'max:255'],
            'is_funded' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'project_involvement' => ['nullable', 'array'],
            'project_involvement.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Get the custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'name.max' => 'Project name may not be greater than 255 characters.',
            'program.required' => 'Program is required.',
            'program.in' => 'Invalid program selected.',
            'is_funded.required' => 'Funding status is required.',
            'is_funded.boolean' => 'Funding status must be true or false.',
            'project_involvement.array' => 'Project involvement must be an array.',
            'project_involvement.*.string' => 'Each project involvement item must be a string.',
            'project_involvement.*.max' => 'Project involvement items may not be greater than 255 characters.',
        ];
    }
}
