<?php

namespace App\Http\Requests;

use App\Enums\ComplianceStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Editing a document is part of preparing it for review; the controller
        // additionally refuses a reviewer and scopes the uploader to their center.
        return $this->user()?->can('submit-documents') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'validity' => ['nullable', 'date'],
            'compliance_status' => ['nullable', 'string', Rule::in(array_keys(ComplianceStatusEnum::labels()))],
            'document_type' => ['nullable', 'string', 'max:255'],
            'center_code' => ['nullable', 'string', 'max:255'],
            'partner_code' => ['nullable', 'string', 'max:255'],
            'farmer_code' => ['nullable', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'file_key' => ['nullable', 'string', 'max:255'],
            'file_type' => ['nullable', 'string', 'max:100'],
            'file_size' => ['nullable', 'integer'],
        ];
    }
}
