<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\HasScopeAccess;

class UserPolicy
{
    use HasScopeAccess;

    /**
     * Determine whether the user can view any users.
     *
     * Gated on 'view-users' rather than a role name, so the permission matrix
     * in config/roles.php is the single source of truth.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->can('view-users');
    }

    /**
     * Determine whether the user can view the target user.
     */
    public function view(User $authUser, User $targetUser): bool
    {
        // A PCC-level actor sees every account in its own agency, including
        // accounts that belong to no center.
        if ($authUser->isPccLevel() && $authUser->agency_id === $targetUser->agency_id) {
            return true;
        }

        return $this->HasScopeAccess($authUser, $targetUser);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('create-users');
    }

    public function update(User $authUser, User $targetUser): bool
    {
        if ($authUser->is($targetUser)) {
            return false;
        }

        return $authUser->can('update-users') && $this->view($authUser, $targetUser);
    }

    /**
     * Accounts are deactivated, never hard-deleted, so the records they own keep
     * a valid author.
     */
    public function delete(User $authUser, User $targetUser): bool
    {
        return $authUser->can('deactivate-users')
            && ! $authUser->is($targetUser)
            && $this->view($authUser, $targetUser);
    }

    public function approve(User $authUser, User $targetUser): bool
    {
        return $authUser->can('approve-users')
            && ! $authUser->is($targetUser)
            && $targetUser->status?->awaitsApproval() === true;
    }

    public function reject(User $authUser, User $targetUser): bool
    {
        return $authUser->can('approve-users')
            && ! $authUser->is($targetUser)
            && $targetUser->status?->awaitsApproval() === true;
    }

    public function restore(User $authUser, User $targetUser): bool
    {
        return $authUser->can('update-users') && $this->view($authUser, $targetUser);
    }

    public function forceDelete(User $authUser, User $targetUser): bool
    {
        // Never. Account history and authored records outlive the account.
        return false;
    }
}
