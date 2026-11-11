<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use App\Enums\Contracts\WithLabels;

enum UserRoleEnum: string implements WithLabels
{
    use HasLabels;

    /**
     * The PCC administrator. Operates agency-wide and is the only role that
     * provisions accounts. Historically labelled "Agency Admin"; the stored
     * value is unchanged so existing logins and pivot rows keep working.
     */
    case AGENCY_ADMIN = 'agency-admin';

    /**
     * Read-only PCC-wide role. Enters no data and provisions no accounts, but
     * may export reports — see docs/plans/user-management-and-pcc-roles.md §4.1.
     */
    case EXECUTIVE = 'executive';

    /**
     * Manages the center and the accounts inside it: edits the center record,
     * manages its farmers and partners, and provisions encoder/reviewer accounts.
     */
    case CENTER_ADMIN = 'center-admin';

    /**
     * The center's data-entry account. Enters production, farmer and partner
     * records and submits documents for review. Holds no user-management
     * permission, so it cannot create or approve anything.
     */
    case ENCODER = 'encoder';

    /**
     * The center's reviewer: approves or rejects documents for their own
     * center and never enters data.
     */
    case REVIEWER = 'reviewer';

    public static function labels(): array
    {
        return [
            self::AGENCY_ADMIN->value => 'PCC Administrator',
            self::EXECUTIVE->value => 'Executive',
            self::CENTER_ADMIN->value => 'Center Admin',
            self::ENCODER->value => 'Encoder',
            self::REVIEWER->value => 'Reviewer',
        ];
    }

    /**
     * Roles that must be pinned to exactly one center.
     *
     * Scoping code keys off this rather than a hardcoded role name, so a new
     * center-level role is scoped by adding it here.
     *
     * @return array<int, string>
     */
    public static function centerScopedValues(): array
    {
        return [
            self::CENTER_ADMIN->value,
            self::ENCODER->value,
            self::REVIEWER->value,
        ];
    }

    /**
     * Center-scoped roles that may also be provisioned agency-wide.
     *
     * An encoder or a reviewer with no center is not a broken account: it is a
     * data-entry or review officer for the whole agency, which is how the PCC
     * staffs a national function. Center Admin is deliberately absent - the
     * center is the thing that role administers.
     *
     * @return array<int, string>
     */
    public static function agencyWideCapableValues(): array
    {
        return [
            self::ENCODER->value,
            self::REVIEWER->value,
        ];
    }

    /**
     * Roles that operate agency-wide and therefore must have no center_id.
     *
     * @return array<int, string>
     */
    public static function pccLevelValues(): array
    {
        return [
            self::AGENCY_ADMIN->value,
            self::EXECUTIVE->value,
        ];
    }

    /**
     * Roles a center-scoped actor is allowed to assign. Keeps a center's
     * reviewer from being handed a PCC-wide role.
     *
     * @return array<int, string>
     */
    public static function assignableByCenterScopedValues(): array
    {
        return self::centerScopedValues();
    }

    public function isCenterScoped(): bool
    {
        return in_array($this->value, self::centerScopedValues(), true);
    }

    /**
     * Whether this role may be provisioned with no center at all, i.e. agency-wide.
     */
    public function canBeAgencyWide(): bool
    {
        return in_array($this->value, self::agencyWideCapableValues(), true);
    }

    public function isPccLevel(): bool
    {
        return in_array($this->value, self::pccLevelValues(), true);
    }

    /**
     * Roles this one is permitted to grant.
     *
     * Only the PCC administrator may hand out PCC-wide roles; everyone else is
     * limited to the center-scoped set.
     *
     * @return array<int, string>
     */
    public function assignableRoles(): array
    {
        return $this->isPccLevel()
            ? array_column(self::cases(), 'value')
            : self::assignableByCenterScopedValues();
    }

    public static function isCenterScopedValue(?string $value): bool
    {
        return $value !== null && in_array($value, self::centerScopedValues(), true);
    }

    public static function isPccLevelValue(?string $value): bool
    {
        return $value !== null && in_array($value, self::pccLevelValues(), true);
    }
}
