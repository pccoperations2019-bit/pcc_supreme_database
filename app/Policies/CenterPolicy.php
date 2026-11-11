<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Center;
use App\Models\User;
use App\Policies\Concerns\HasScopeAccess;

class CenterPolicy
{
    use HasScopeAccess;

    /**
     * Determine whether the user can list centers.
     *
     * A center admin reaches its own record through centers.show, so it does not
     * need the list either. What separates the roles that keep it from the ones
     * that do not is the center: an encoder or reviewer provisioned agency-wide is
     * a PCC officer with nationwide reach and keeps the list, while the same roles
     * pinned to one center are confined to that center and are refused the page.
     *
     * Denying the page is not what confines them - CenterController::index also
     * narrows the query with visibleTo() - it only keeps them off a page that has
     * nothing to show them.
     */
    public function viewAny(User $user): bool
    {
        if ($user->isPccLevel() || $user->isAgencyWideDataEntry()) {
            return true;
        }

        // Center Admin is the exception: it administers its own center and reaches
        // it directly, so it is left the list too rather than special-cased out.
        return $user->hasRole(UserRoleEnum::CENTER_ADMIN->value);
    }

    /**
     * Determine whether the user can view the center.
     */
    public function view(User $user, Center $center): bool
    {
        return $this->HasScopeAccess($user, $center);
    }

    /**
     * Determine whether the user can create centers.
     */
    public function create(User $user): bool
    {
        return $user->can('manage-center');
    }

    /**
     * Determine whether the user can update the center.
     */
    public function update(User $user, Center $center): bool
    {
        return $this->HasScopeAccess($user, $center);
    }

    /**
     * Determine whether the user can delete the center.
     */
    public function delete(User $user, Center $center): bool
    {
        return $this->HasScopeAccess($user, $center);
    }

    /**
     * Determine whether the user can restore the center.
     */
    public function restore(User $user, Center $center): bool
    {
        return $this->HasScopeAccess($user, $center);
    }

    /**
     * Determine whether the user can permanently delete the center.
     */
    public function forceDelete(User $user, Center $center): bool
    {
        return $this->HasScopeAccess($user, $center);
    }
}
