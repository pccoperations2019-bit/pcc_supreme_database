<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToCenter
{
    /**
     * Restrict the query to what the given user is allowed to see.
     *
     * Keys off `User::isCenterScoped()` rather than a hardcoded role name so
     * that every center-level role is scoped by adding it to UserRoleEnum,
     * instead of silently inheriting agency-wide visibility if it is missed.
     */
    protected function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User || ! $user->isCenterScoped()) {
            return $query;
        }

        // A center-scoped role with no center_id cannot be pinned to anything,
        // so it sees nothing rather than everything.
        if ($user->center_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->getTable().'.'.$this->getCenterColumn(), $user->center_id);
    }

    protected function getCenterColumn(): string
    {
        return 'center_id';
    }
}
