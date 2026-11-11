<?php

namespace App\Policies;

use App\Models\MeatProduction;
use App\Models\User;

class MeatProductionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MeatProduction $meatProduction): bool
    {
        return $this->belongsToUsersCenter($user, $meatProduction);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MeatProduction $meatProduction): bool
    {
        return $this->belongsToUsersCenter($user, $meatProduction);
    }

    public function delete(User $user, MeatProduction $meatProduction): bool
    {
        return $this->belongsToUsersCenter($user, $meatProduction);
    }

    private function belongsToUsersCenter(User $user, MeatProduction $meatProduction): bool
    {
        return ! $user->isCenterScoped()
            || $user->center_id === $meatProduction->center_id;
    }
}
