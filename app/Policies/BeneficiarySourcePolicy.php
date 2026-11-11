<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\BeneficiarySource;
use App\Models\User;

class BeneficiarySourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPccLevel();
    }

    public function view(User $user, BeneficiarySource $source): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('enter-data')
            && ($user->hasRole(UserRoleEnum::AGENCY_ADMIN->value)
                || ($user->hasRole(UserRoleEnum::ENCODER->value) && $user->isAgencyWideDataEntry()));
    }

    public function update(User $user, BeneficiarySource $source): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, BeneficiarySource $source): bool
    {
        return $this->create($user);
    }
}
