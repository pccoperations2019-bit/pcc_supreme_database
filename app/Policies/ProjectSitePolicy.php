<?php

namespace App\Policies;

use App\Models\ProjectSite;
use App\Models\User;

class ProjectSitePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProjectSite $projectSite): bool
    {
        return $this->belongsToUsersCenter($user, $projectSite);
    }

    /**
     * Centers may enter their own project sites. `center_id` is pinned to the
     * signed-in center by ForcesCenterForCenterScopedUser, so this cannot be used to
     * file a site under another center.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProjectSite $projectSite): bool
    {
        return $this->belongsToUsersCenter($user, $projectSite);
    }

    public function delete(User $user, ProjectSite $projectSite): bool
    {
        return $this->belongsToUsersCenter($user, $projectSite);
    }

    private function belongsToUsersCenter(User $user, ProjectSite $projectSite): bool
    {
        return ! $user->isCenterScoped()
            || $user->center_id === $projectSite->center_id;
    }
}
