<?php

namespace App\Http\Requests;

use App\Enums\ProfileStatusEnum;
use App\Enums\ProgramEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCenterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Editing a center's own record is a management action; an encoder holds
        // no 'manage-center' and is confined to its own data-entry records.
        return $this->user()?->can('manage-center') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProfileStatusEnum::labels()))],
            'website' => ['nullable', 'string'],
            'date_of_establishment' => ['nullable', 'date'],
            'date_assisted' => ['nullable', 'date'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
            'telephone_number' => ['nullable', 'string', 'max:255'],
            'email_address' => ['nullable', 'email', 'max:255'],
            'program' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProgramEnum::labels()))],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'logo' => ['nullable', 'image', 'max:2048'],
            // Address fields morph
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            // Animal Inventory (fixed repeating table)
            'animal_inventory' => ['nullable', 'array', 'max:12'],
            'animal_inventory.*.classification' => ['required', 'string', Rule::in([
                'Cow Lactating',
                'Cow Lactating Pregnant',
                'Cow Dry Pregnant',
                'Cow Dry',
                'Heifer Pregnant',
                'Heifer',
                'Sr. Bull (more than 3 yrs)',
                'Jr. Bull (2-3 yrs.)',
                'Yearling Female (1 yr - 2 yrs)',
                'Yearling Male (1 yr - 2 yrs)',
                'Female Calf (less than 1 year)',
                'Male Calf (less than 1 year)',
            ])],
            'animal_inventory.*.count' => ['nullable', 'integer', 'min:0'],
            'animal_inventory.*.breed' => ['nullable', 'string', Rule::in(['Native', 'CB', 'Purebred'])],
            'animal_inventory.*.remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
