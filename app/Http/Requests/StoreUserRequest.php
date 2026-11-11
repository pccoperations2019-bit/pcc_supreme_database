<?php

namespace App\Http\Requests;

use App\Enums\UserRoleEnum;
use App\Models\Center;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-users') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'min:3', 'max:255', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ];
    }

    /**
     * The role the submitted value resolves to, or null when absent/invalid.
     */
    public function roleEnum(): ?UserRoleEnum
    {
        return UserRoleEnum::tryFrom((string) $this->input('role'));
    }

    /**
     * Enforce the rule the permission matrix cannot express: a center-scoped
     * role needs exactly one center, a PCC-wide role must have none.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $role = $this->roleEnum();

            if (! $role instanceof UserRoleEnum) {
                return;
            }

            $center = $this->resolvedCenter();

            // An encoder or a reviewer may be provisioned agency-wide, which is
            // what a PCC-wide data-entry or review officer is. Every other
            // center-scoped role - the Center Admin - is defined by its center.
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

            // A center-scoped actor may only ever create users in their own
            // center, whatever center_id the client submits.
            if ($role->isCenterScoped()
                && $this->user()?->isCenterScoped()
                && $center instanceof Center
                && $center->id !== $this->user()->center_id) {
                $validator->errors()->add('center_id', 'You may only create users in your own center.');
            }

            // ...and may not reach outside their own agency.
            if ($center instanceof Center && $this->user()?->agency_id !== null
                && $center->agency_id !== $this->user()->agency_id) {
                $validator->errors()->add('center_id', 'That center belongs to another agency.');
            }
        });
    }

    /**
     * The center being assigned, forced to the actor's own center when the actor
     * is center-scoped, so the scoping rule holds even before validation runs.
     */
    public function resolvedCenter(): ?Center
    {
        if ($this->user()?->isCenterScoped()) {
            return $this->user()->center_id === null
                ? null
                : Center::find($this->user()->center_id);
        }

        $centerId = $this->input('center_id');

        return $centerId === null ? null : Center::find($centerId);
    }

    /**
     * The roles this actor is permitted to grant. Only a PCC-level role may hand
     * out other PCC-level roles.
     *
     * @return array<int, string>
     */
    private function assignableRoles(): array
    {
        $actorRole = $this->actorRoleEnum();

        return $actorRole?->assignableRoles() ?? [];
    }

    private function actorRoleEnum(): ?UserRoleEnum
    {
        foreach ($this->user()?->knownRoles() ?? [] as $role) {
            if ($role->isPccLevel()) {
                return $role;
            }
        }

        return $this->user()?->knownRoles()[0] ?? null;
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
