<?php

namespace App\Policies;

use App\Models\MeatSales;
use App\Models\User;

class MeatSalesPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MeatSales $meatSales): bool
    {
        return $this->belongsToUsersCenter($user, $meatSales);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MeatSales $meatSales): bool
    {
        return $this->belongsToUsersCenter($user, $meatSales);
    }

    public function delete(User $user, MeatSales $meatSales): bool
    {
        return $this->belongsToUsersCenter($user, $meatSales);
    }

    private function belongsToUsersCenter(User $user, MeatSales $meatSales): bool
    {
        return ! $user->isCenterScoped()
            || $user->center_id === $meatSales->center_id;
    }
}
