<?php

namespace App\Models;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Concerns\LogsAuditTrail;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsAuditTrail, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Guarded so the approval columns can only be written through
     * UserProvisioningService / UserController, never mass assignment.
     *
     * @var list<string>
     */
    protected $guarded = [
        'status',
        'approved_by',
        'approved_at',
        'agency_id',
        'center_id',
    ];

    /**
     * A password change leaves no loggable field behind, because the password is
     * one of the excluded attributes. Submitting the row anyway keeps "this
     * account's credentials were changed" in the audit trail.
     */
    protected function auditSubmitsEmptyLogs(): bool
    {
        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatusEnum::class,
            'approved_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * The user who approved this account, if any.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    public function canAuthenticate(): bool
    {
        return $this->status?->canAuthenticate() ?? false;
    }

    /**
     * Whether this user may export data spanning every center.
     *
     * An agency-wide encoder is the one case where the answer is not simply the
     * permission: the encoder role only holds 'export-own-center', which cannot
     * apply to an account with no center. Rather than grant 'export-data' to the
     * whole role - which would hand every center's encoder a nationwide export -
     * the wider reach is granted to the account, here, on the strength of its
     * scope.
     */
    public function canExportData(): bool
    {
        return $this->can('export-data') || $this->isAgencyWideDataEntry();
    }

    /**
     * Whether this user may export data confined to its own center.
     *
     * Deliberately separate from canExportData(): the pivot builder aggregates
     * every center and stays PCC-level, while an encoder still needs to pull its
     * own center's reports. Callers must additionally pin the record set to
     * $this->center_id, because holding this permission alone is not a scope.
     */
    public function canExportOwnCenter(): bool
    {
        return $this->can('export-own-center')
            && $this->isCenterScoped()
            && $this->center_id !== null;
    }

    /**
     * Whether this user may export at all, at either breadth.
     */
    public function canExportSomething(): bool
    {
        return $this->canExportData() || $this->canExportOwnCenter();
    }

    /**
     * Whether this user is confined to a single center.
     *
     * A center-scoped role with no center is not confined to anything: it was
     * provisioned agency-wide, so it is reported as not center-scoped and
     * therefore reaches every center (see isPccLevel()). Scoping code keys off
     * this rather than a hardcoded role name, so a new center-level role is
     * scoped by adding it to UserRoleEnum.
     */
    public function isCenterScoped(): bool
    {
        return $this->hasRole(UserRoleEnum::centerScopedValues())
            && $this->center_id !== null;
    }

    /**
     * Whether this account is a data-entry or review officer for the whole agency
     * rather than for one center.
     */
    public function isAgencyWideDataEntry(): bool
    {
        return $this->center_id === null
            && $this->hasRole(UserRoleEnum::agencyWideCapableValues());
    }

    /**
     * Whether this user operates agency-wide and therefore has no center.
     *
     * True for a PCC-wide role, and for an agency-wide encoder or reviewer: both
     * reach every center in the agency, which is what the filters, the nav and
     * HasScopeAccess need to know.
     */
    public function isPccLevel(): bool
    {
        return $this->hasRole(UserRoleEnum::pccLevelValues())
            || $this->isAgencyWideDataEntry();
    }

    /**
     * The user's roles that this application recognises, ignoring any
     * permission-package role added out of band.
     *
     * @return array<int, UserRoleEnum>
     */
    public function knownRoles(): array
    {
        return array_values(array_filter(
            array_map(UserRoleEnum::tryFrom(...), $this->getRoleNames()->all())
        ));
    }
}
