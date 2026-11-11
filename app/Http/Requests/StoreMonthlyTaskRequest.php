<?php

namespace App\Http\Requests;

use App\Enums\MonthlyTaskProductEnum;
use App\Enums\MonthlyTaskTypeEnum;
use App\Enums\ProgramEnum;
use App\Http\Requests\Concerns\ForcesCenterForCenterScopedUser;
use App\Models\MonthlyTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMonthlyTaskRequest extends FormRequest
{
    use ForcesCenterForCenterScopedUser;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('generate-monthly-tasks')) {
            return false;
        }

        if ($user->isCenterScoped() && $user->center_id !== (int) $this->input('center_id')) {
            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->forceCenterForCenterScopedUser();
    }

    public function rules(): array
    {
        return [
            'center_id' => ['required', 'integer', 'exists:centers,id'],
            'program' => ['required', 'string', Rule::in(ProgramEnum::values())],
            'product_type' => ['required', 'string', Rule::in(MonthlyTaskProductEnum::values())],
            'task_type' => ['required', 'string', Rule::in(MonthlyTaskTypeEnum::values())],
            'year' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'product_type' => 'product',
            'task_type' => 'task type',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $exists = MonthlyTask::query()
                ->forCenter((int) $this->input('center_id'))
                ->forProgram((string) $this->input('program'))
                ->forProduct((string) $this->input('product_type'))
                ->forTaskType((string) $this->input('task_type'))
                ->forMonth((int) $this->input('year'), (int) $this->input('month'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('month', 'A task for this product and month already exists.');
            }
        });
    }
}
