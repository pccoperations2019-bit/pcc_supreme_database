<?php

namespace App\Http\Requests;

use App\Enums\UserRoleEnum;
use App\Models\Center;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update-users') ?? false;
    }

    /**
     * An empty password field means "leave the credentials alone", so normalise
     * the blank string a form always submits to null before the nullable rule
     * sees it.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('password') === '') {
            $this->merge(['password' => null, 'password_confirmation' => null]);
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'username' => ['required', 'string', 'min:3', 'max:255', 'alpha_dash', Rule::unique('users', 'username')->ignore($userId)],
            // Omitted on edit: a blank field leaves the existing password alone,
            // so an admin never has to reset someone's credentials to change a role.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', Rule::in($this->assignableRoles())],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ];
    }

    public function roleEnum(): ?UserRoleEnum
    {
        $role = $this->input('role');

        return $role === null ? null : UserRoleEnum::tryFrom((string) $role);
    }

    /**
     * The role the account should end up with: the submitted one, or the
     * existing one when the field was left blank.
     */
    public function resolvedRoleEnum(): ?UserRoleEnum
    {
        if ($this->roleEnum() instanceof UserRoleEnum) {
            return $this->roleEnum();
        }

        return $this->route('user')?->knownRoles()[0] ?? null;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $role = $this->resolvedRoleEnum();

            if (! $role instanceof UserRoleEnum) {
                return;
            }

            $center = $this->resolvedCenter();

            // An encoder or a reviewer may be agency-wide; every other
            // center-scoped role is defined by its center.
            $needsCenter = $role->isCenterScoped() && ! $role->canBeAgencyWide();

            if ($needsCenter && ! $center instanceof Center) {
                $validator->errors()->add(
                    'center_id',
                    "The {$role->label()} role works within a single center, so a center is required."
                );
            }

            if (! $role->isCenterScoped() && $center instanceof Center) {
                $validator->errors()->add(
                    'center_id',
                    "The {$role->label()} role works across the whole agency and cannot be tied to a center."
                );
            }

            if ($center instanceof Center && $this->user()?->agency_id !== null
                && $center->agency_id !== $this->user()->agency_id) {
                $validator->errors()->add('center_id', 'That center belongs to another agency.');
            }

            // A center-scoped actor may only ever manage users in their own center.
            if ($this->user()?->isCenterScoped()
                && $center instanceof Center
                && $center->id !== $this->user()->center_id) {
                $validator->errors()->add('center_id', 'You may only manage users in your own center.');
            }

            // Changing a role needs 'assign-roles'; editing a profile does not.
            $currentRole = $this->route('user')?->knownRoles()[0] ?? null;
            $submittedRole = $this->roleEnum();

            if ($submittedRole instanceof UserRoleEnum
                && $submittedRole !== $currentRole
                && ! $this->user()?->can('assign-roles')) {
                $validator->errors()->add('role', 'You are not allowed to change roles.');
            }
        });
    }

    /**
     * The center the account should end up in.
     *
     * Falls back to the account's current center when the field is absent, so
     * changing a name does not demand the center be re-submitted. An explicit
     * null is respected, which is how a center-scoped role is released.
     */
    public function resolvedCenter(): ?Center
    {
        if ($this->user()?->isCenterScoped()) {
            return $this->user()->center_id === null
                ? null
                : Center::find($this->user()->center_id);
        }

        if (! $this->has('center_id')) {
            return $this->route('user')?->center;
        }

        $centerId = $this->input('center_id');

        return $centerId === null ? null : Center::find($centerId);
    }

    /**
     * @return array<int, string>
     */
    private function assignableRoles(): array
    {
        $actor = $this->user();
        $actorRole = null;

        foreach ($actor?->knownRoles() ?? [] as $role) {
            if ($role->isPccLevel()) {
                $actorRole = $role;
                break;
            }
        }

        return $actorRole?->assignableRoles() ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already in use.',
            'username.unique' => 'This username is already taken.',
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes and underscores.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
            'role.in' => 'You are not allowed to assign that role.',
        ];
    }
}
