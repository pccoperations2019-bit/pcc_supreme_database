<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pins `center_id` on a FormRequest to a center-scoped user's own center, so a
 * center-level user can never create or move a record into another center
 * regardless of what the client submits.
 *
 * Covers every center-scoped role (encoder and reviewer), not just the encoder,
 * so a reviewer is confined to their own center when acting.
 *
 * The consuming request calls `forceCenterForCenterScopedUser()` from its
 * `prepareForValidation()`.
 *
 * @mixin FormRequest
 */
trait ForcesCenterForCenterScopedUser
{
    protected function forceCenterForCenterScopedUser(): void
    {
        $user = $this->user();

        if ($user === null || ! $user->isCenterScoped()) {
            return;
        }

        // A center-scoped role with no center cannot be pinned to anything.
        // Deny rather than merge null, which would create an orphan record.
        abort_if(
            $user->center_id === null,
            403,
            'Your account is not assigned to a center.'
        );

        $this->merge(['center_id' => $user->center_id]);
    }
}
