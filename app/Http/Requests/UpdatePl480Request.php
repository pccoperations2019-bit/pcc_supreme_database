<?php

namespace App\Http\Requests;

use App\Enums\PL480ProvinceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

class UpdatePl480Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentYear = Date::now()->year;

        return [
            'province' => ['required', 'string', 'max:255', Rule::in(PL480ProvinceEnum::values())],
            'year' => ['required', 'integer', 'min:1900', 'max:'.$currentYear],
            'value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
