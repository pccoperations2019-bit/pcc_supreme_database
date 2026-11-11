<?php

namespace Tests\Feature\Users;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Shared fixtures for the user management tests.
 *
 * Roles and permissions are created straight from config/roles.php rather than
 * hand-listed, so these tests fail if the matrix and the config ever disagree.
 */
trait InteractsWithUsers
{
    /** Create every role and permission declared in config/roles.php. */
    protected function seedRolesAndPermissions(): void
    {
        $config = config('roles');

        foreach ($config['permissions'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ($config['roles'] as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }
    }

    protected function agency(string $code = 'PCC'): Agency
    {
        // AgencyFactory is stale against the current schema, so build the row directly.
        return Agency::create(['code' => $code, 'name' => $code.' Agency']);
    }

    protected function center(Agency $agency, string $code = 'MMSU'): Center
    {
        return Center::create([
            'agency_id' => $agency->id,
            'code' => $code,
            'name' => $code.' Center',
        ]);
    }

    /**
     * A user holding the given role, active by default so it can sign in.
     */
    protected function userWithRole(
        UserRoleEnum $role,
        ?Agency $agency = null,
        ?Center $center = null,
        UserStatusEnum $status = UserStatusEnum::ACTIVE,
    ): User {
        $user = User::factory()->create([
            'username' => Str::lower($role->value).'-'.Str::random(6),
            'agency_id' => $agency?->id,
            'center_id' => $center?->id,
            'status' => $status,
        ]);

        $user->assignRole($role->value);

        return $user->fresh();
    }

    /**
     * The PCC administrator who provisions accounts.
     */
    protected function pccAdmin(?Agency $agency = null): User
    {
        return $this->userWithRole(UserRoleEnum::AGENCY_ADMIN, $agency ?? $this->agency());
    }

    /**
     * A valid payload for the store endpoint.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function userPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Encoder One',
            'email' => 'encoder.one@pcc.gov.ph',
            'username' => 'encoder_one',
            'password' => 'RcXA3HcPwo0W',
            'password_confirmation' => 'RcXA3HcPwo0W',
            'role' => UserRoleEnum::CENTER_ADMIN->value,
        ], $overrides);
    }
}
