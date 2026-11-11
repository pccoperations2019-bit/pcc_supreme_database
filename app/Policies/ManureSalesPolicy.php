<?php

namespace App\Policies;

use App\Models\ManureSales;
use App\Models\User;

class ManureSalesPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ManureSales $manureSales): bool
    {
        return $this->belongsToUsersCenter($user, $manureSales);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ManureSales $manureSales): bool
    {
        return $this->belongsToUsersCenter($user, $manureSales);
    }

    public function delete(User $user, ManureSales $manureSales): bool
    {
        return $this->belongsToUsersCenter($user, $manureSales);
    }

    private function belongsToUsersCenter(User $user, ManureSales $manureSales): bool
    {
        return ! $user->isCenterScoped()
            || $user->center_id === $manureSales->center_id;
    }
}
