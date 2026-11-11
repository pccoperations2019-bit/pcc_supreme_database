<?php

namespace App\Http\Requests;

use App\Enums\MilkFeedingProgramFunderEnum;
use App\Models\BeneficiarySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BeneficiarySourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('beneficiary_source');

        return $source instanceof BeneficiarySource
            ? ($this->user()?->can('update', $source) ?? false)
            : ($this->user()?->can('create', BeneficiarySource::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $name = $this->input('name');

        if (is_string($code)) {
            $code = trim($code);
            $source = MilkFeedingProgramFunderEnum::tryFroMatches($code);
            $this->merge(['code' => $source?->value ?? $code]);

            if ($source instanceof MilkFeedingProgramFunderEnum) {
                $name = MilkFeedingProgramFunderEnum::beneficiaryName()[$source->value];
            }
        }

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', Rule::enum(MilkFeedingProgramFunderEnum::class),
                Rule::unique('beneficiary_sources', 'code')
                    ->where('year', $this->input('year'))
                    ->ignore($this->route('beneficiary_source')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'amount' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => 'A beneficiary source already exists for this source code and year.'];
    }
}
