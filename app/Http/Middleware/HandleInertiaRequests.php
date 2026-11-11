<?php

namespace App\Http\Middleware;

use App\Enums\CurrencyEnum;
use App\Enums\ProgramEnum;
use App\Models\BeneficiarySource;
use App\Models\User;
use App\Support\Centers;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Roles and permissions must be loaded here, not lazily: useAuth() reads
        // `auth.user.roles` on the client, and an unloaded relation serialises as
        // null, which made every frontend hasRole() check silently false.
        if ($user !== null) {
            $user->loadMissing('roles.permissions');
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            // Top level, because MainNav.vue reads `page.props.permissions`.
            // A flat list of permission names the signed-in user holds.
            //
            // 'export-data' is added for an agency-wide encoder: the encoder role
            // only holds 'export-own-center', which cannot apply to an account
            // with no center, so User::canExportData() widens it. The nav and
            // useAuth() read this list, so it has to carry the same answer.
            'permissions' => $this->permissionsFor($user),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            '_program' => session('_program'),
            '_center_id' => session('_center_id'),
            '_milk_productions_view' => session('_milk_productions_view'),
            'programs' => ProgramEnum::options(),
            'filters' => [
                'clusteredCenters' => Centers::clustersForFilter(
                    ($request->all()['filter'] ?? [])['program'] ?? null,
                    // The dashboard switches national centers per program
                    // (IH → GP, CBED → NIZ); other pages show all of them.
                    $request->routeIs('dashboard')
                        ? session('_program') ?? ProgramEnum::CBED->value
                        : null,
                ),
            ],
            'currencySymbols' => CurrencyEnum::symbols(),
            'csrf_token' => csrf_token(),
        ];
    }

    /**
     * The permission names the client should see the signed-in user as holding.
     *
     * The role permissions, plus 'export-data' for an account whose reach is wider
     * than its role's default (an agency-wide encoder, which User::canExportData()
     * grants on scope rather than on permission).
     *
     * @return array<int, string>
     */
    private function permissionsFor(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        if ($user->isAgencyWideDataEntry() && ! in_array('export-data', $permissions, true)) {
            $permissions[] = 'export-data';
        }

        // Derived from the policy because agency encoders and center encoders
        // share a role and enter-data permission, but have different access here.
        if ($user->can('viewAny', BeneficiarySource::class)) {
            $permissions[] = 'view-beneficiary-sources';
        }
        if ($user->can('create', BeneficiarySource::class)) {
            $permissions[] = 'manage-beneficiary-sources';
        }

        return $permissions;
    }
}
