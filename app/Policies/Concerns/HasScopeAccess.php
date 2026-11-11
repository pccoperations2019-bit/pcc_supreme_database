<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait HasScopeAccess
{
    /**
     * Determine whether the authenticated user has scoped access
     * to the given model (Center, User, Farmer, Partner, etc.).
     *
     * Grants on two axes, both taken from the user's data rather than a role
     * name: PCC-level users reach their own agency, center-scoped users reach
     * their own center. Anyone else is denied.
     */
    protected function HasScopeAccess(User $authUser, $model): bool
    {
        $agencyId = $this->resolveAgencyId($model);
        $centerId = $this->resolveCenterId($model);

        if ($authUser->isPccLevel()) {
            return $authUser->agency_id !== null && $authUser->agency_id === $agencyId;
        }

        if ($authUser->isCenterScoped()) {
            return $authUser->center_id !== null && $authUser->center_id === $centerId;
        }

        return false;
    }

    /**
     * Resolve the agency that owns the target row.
     *
     * A user with no center (a PCC-level or read-only account) has no
     * `center->agency_id` fallback, so this must not assume `$model->center`
     * is loaded — that read is what previously warned on null.
     */
    protected function resolveAgencyId($model): mixed
    {
        $agencyId = $model->agency_id ?? null;

        if ($agencyId !== null) {
            return $agencyId;
        }

        $center = $model->center ?? null;

        return $center?->agency_id;
    }

    /**
     * Resolve the center that owns the target row.
     */
    protected function resolveCenterId($model): mixed
    {
        return $model->center_id ?? ($model->id ?? null);
    }
}
