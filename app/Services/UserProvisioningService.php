<?php

namespace App\Services;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single place a user account is created, approved, or deactivated.
 *
 * Three callers share this: UserController, the center-creation flow (which
 * used to build its admin inline and so bypassed the approval gate entirely),
 * and tests. Keeping the scope rules here means a center can never be attached
 * to a PCC-wide role, and a new caller cannot accidentally skip the status
 * bookkeeping.
 *
 * The audit row comes free from the User model's LogsAuditTrail trait, which
 * logs un-guarded changes and excludes the password.
 */
class UserProvisioningService
{
    /**
     * Create a user with a consistent role/scope pair.
     *
     * @throws InvalidArgumentException when the role and center disagree.
     */
    public function create(
        string $name,
        string $email,
        string $username,
        string $password,
        UserRoleEnum $role,
        ?Center $center = null,
        UserStatusEnum $status = UserStatusEnum::PENDING,
        ?Agency $agency = null,
    ): User {
        $this->assertScopeMatchesRole($role, $center);

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->username = $username;
        // Hashed by the model's 'hashed' cast, so this stays plaintext here.
        $user->password = $password;
        $user->status = $status;

        // Approved up front only when explicitly asked, e.g. the very first
        // administrator bootstrapping the system.
        if ($status === UserStatusEnum::ACTIVE) {
            $user->approved_at = now();
        }

        $agency ??= $center?->agency;

        if ($agency !== null && $agency->getKey() !== null) {
            $user->agency()->associate($agency);
        }

        if ($center instanceof Center && $center->getKey() !== null) {
            $user->center()->associate($center);
        }

        $user->save();

        $user->syncRoles([$role->value]);

        return $user;
    }

    /**
     * A PCC-wide role must have no center; a center-scoped role must have one —
     * unless it is one of the two roles that may also be provisioned agency-wide,
     * where no center is the point.
     *
     * @throws InvalidArgumentException
     */
    public function assertScopeMatchesRole(UserRoleEnum $role, ?Center $center): void
    {
        $hasCenter = $center instanceof Center && $center->getKey() !== null;

        if ($role->isCenterScoped() && ! $hasCenter && ! $role->canBeAgencyWide()) {
            throw new InvalidArgumentException(
                "The [{$role->value}] role is center-scoped and requires a center."
            );
        }

        if (! $role->isCenterScoped() && $hasCenter) {
            throw new InvalidArgumentException(
                "The [{$role->value}] role operates agency-wide and cannot be attached to a center."
            );
        }
    }

    /**
     * Activate a pending or rejected account.
     */
    public function approve(User $user, User $approver): User
    {
        if (! $user->status?->awaitsApproval()) {
            throw new InvalidArgumentException(
                "This account is already {$user->status?->value} and cannot be approved."
            );
        }

        $user->status = UserStatusEnum::ACTIVE;
        $user->approved_by = $approver->getKey();
        $user->approved_at = now();
        $user->save();

        return $user;
    }

    /**
     * Refuse an account. The creator may edit it and resubmit.
     */
    public function reject(User $user, User $approver): User
    {
        if (! $user->status?->awaitsApproval()) {
            throw new InvalidArgumentException(
                "This account is {$user->status?->value} and is not awaiting approval."
            );
        }

        $user->status = UserStatusEnum::REJECTED;
        $user->approved_by = $approver->getKey();
        $user->approved_at = now();
        $user->save();

        return $user;
    }

    /**
     * Disable an account without touching the records it owns.
     */
    public function deactivate(User $user): User
    {
        $user->status = UserStatusEnum::DEACTIVATED;
        $user->save();

        return $user;
    }

    /**
     * Return a deactivated account to service.
     *
     * Deactivation is reversible: without this, `deactivate` is a one-way door and
     * a mistyped or regretted deactivation locks the account out permanently. The
     * approval columns are refreshed so the audit trail shows who brought it back.
     */
    public function reactivate(User $user, User $actor): User
    {
        $user->status = UserStatusEnum::ACTIVE;
        $user->approved_by = $actor->getKey();
        $user->approved_at = now();
        $user->save();

        return $user;
    }

    /**
     * The user who created this account, read back from the activity log rather
     * than a dedicated column, since the model's audit trail already records it.
     */
    public function creatorOf(User $user): ?User
    {
        $causerId = DB::table(config('activitylog.table_name'))
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->getKey())
            ->where('event', 'created')
            ->orderBy('id')
            ->value('causer_id');

        return $causerId === null ? null : User::find($causerId);
    }

    /**
     * Separation of duties: whoever created an account may not approve it.
     */
    public function createdBy(User $actor, User $target): bool
    {
        return $this->creatorOf($target)?->is($actor) ?? false;
    }

    /**
     * Whether another *active* account is capable of approving.
     *
     * Only the PCC administrator holds 'approve-users', so in a deployment with
     * a single such account the strict "creator may not approve" rule deadlocks:
     * that account can never activate anything, including a second approver, so
     * no second approver can ever become active. The rule therefore has to yield
     * when nobody else is able to make the decision — the audit trail still
     * records who approved what.
     */
    public function hasAnotherApprover(User $actor): bool
    {
        return User::query()
            ->whereKeyNot($actor->getKey())
            ->where('status', UserStatusEnum::ACTIVE->value)
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'approve-users'))
            ->exists();
    }

    /**
     * Whether this actor may decide on $target, given they may be its creator.
     *
     * Allowed when the actor did not create it, or when no other active account
     * could make the decision instead. Blocked only when the actor created it
     * *and* a second approver exists to hand it to.
     */
    public function mayDecideOn(User $actor, User $target): bool
    {
        if (! $this->createdBy($actor, $target)) {
            return true;
        }

        return ! $this->hasAnotherApprover($actor);
    }
}
