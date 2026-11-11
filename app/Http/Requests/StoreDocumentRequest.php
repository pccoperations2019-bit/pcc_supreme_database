<?php

namespace App\Http\Requests;

use App\Enums\ComplianceStatusEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Uploading is what puts a document into the review queue, so it needs
        // 'submit-documents'. The reviewer holds only 'review-documents' and is
        // refused here rather than in the controller.
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
            'file_name' => ['required', 'string', 'max:255'],
            'file_key' => ['required', 'string', 'max:255'],
            'file_type' => ['required', 'string', 'max:100'],
            'file_size' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*.key' => ['required_with:tags', 'string', 'max:255'],
            'tags.*.value' => ['required_with:tags', 'string', 'max:255'],
            'tags.*.taggable_type' => ['nullable', 'string', 'max:255'],
            'tags.*.taggable_id' => ['nullable', 'integer'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'validity' => ['nullable', 'date'],
            'compliance_status' => ['nullable', 'string', Rule::in(array_keys(ComplianceStatusEnum::labels()))],
        ];
    }
}
