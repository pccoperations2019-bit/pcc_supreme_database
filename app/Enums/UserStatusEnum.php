<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum UserStatusEnum: string implements WithLabels
{
    use HasLabels;

    const DEFAULT = self::ACTIVE;

    /** Provisioned but not yet approved; cannot authenticate. */
    case PENDING = 'pending';

    /** Approved and able to authenticate. */
    case ACTIVE = 'active';

    /** Approval was refused; the creator may edit and resubmit. */
    case REJECTED = 'rejected';

    /** Was active, now disabled; records are retained. */
    case DEACTIVATED = 'deactivated';

    public static function labels(): array
    {
        return [
            self::PENDING->value => 'Pending',
            self::ACTIVE->value => 'Active',
            self::REJECTED->value => 'Rejected',
            self::DEACTIVATED->value => 'Deactivated',
        ];
    }

    /**
     * Statuses an account may authenticate with.
     *
     * @return array<int, string>
     */
    public static function authenticatingValues(): array
    {
        return [self::ACTIVE->value];
    }

    /**
     * Statuses that still await an approval decision.
     *
     * @return array<int, string>
     */
    public static function awaitingApproval(): array
    {
        return [self::PENDING->value, self::REJECTED->value];
    }

    public function canAuthenticate(): bool
    {
        return in_array($this->value, self::authenticatingValues(), true);
    }

    /**
     * Whether the account is waiting on an approval decision, and therefore
     * whether an approver may still act on it.
     */
    public function awaitsApproval(): bool
    {
        return in_array($this->value, self::awaitingApproval(), true);
    }
}
