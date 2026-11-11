<?php

namespace App\Policies;

use App\Models\ManureProduction;
use App\Models\User;

class ManureProductionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ManureProduction $manureProduction): bool
    {
        return $this->belongsToUsersCenter($user, $manureProduction);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ManureProduction $manureProduction): bool
    {
        return $this->belongsToUsersCenter($user, $manureProduction);
    }

    public function delete(User $user, ManureProduction $manureProduction): bool
    {
        return $this->belongsToUsersCenter($user, $manureProduction);
    }

    private function belongsToUsersCenter(User $user, ManureProduction $manureProduction): bool
    {
        return ! $user->isCenterScoped()
            || $user->center_id === $manureProduction->center_id;
    }
}
