<?php

namespace App\Policies;

use App\Models\MilkProduction;
use App\Models\User;

class MilkProductionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MilkProduction $milkProduction): bool
    {
        return $this->belongsToUsersCenter($user, $milkProduction);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MilkProduction $milkProduction): bool
    {
        return $this->belongsToUsersCenter($user, $milkProduction);
    }

    public function delete(User $user, MilkProduction $milkProduction): bool
    {
        return $this->belongsToUsersCenter($user, $milkProduction);
    }

    private function belongsToUsersCenter(User $user, MilkProduction $milkProduction): bool
    {
        return ! $user->isCenterScoped()
            || $user->center_id === $milkProduction->center_id;
    }
}
