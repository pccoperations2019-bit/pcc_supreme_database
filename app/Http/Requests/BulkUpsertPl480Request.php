<?php

namespace App\Http\Requests;

use App\Enums\PL480ProvinceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

class BulkUpsertPl480Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentYear = Date::now()->year;

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.province' => ['required', 'string', 'max:255', Rule::in(PL480ProvinceEnum::values())],
            'items.*.year' => ['required', 'integer', 'min:1900', 'max:'.$currentYear],
            'items.*.value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
