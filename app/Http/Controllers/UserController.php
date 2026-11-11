<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Agency;
use App\Models\Center;
use App\Models\User;
use App\Services\UserProvisioningService;
use App\Support\Centers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    public function __construct(
        private UserProvisioningService $provisioning,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::class)
            ->with(['roles:id,name', 'center:id,name,code', 'agency:id,name,code'])
            ->select(['id', 'name', 'email', 'username', 'status', 'agency_id', 'center_id', 'approved_by', 'approved_at', 'created_at'])
            ->when($request->get('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            // NHQ is hidden in every context, so its accounts are not listed.
            // Accounts with no center (PCC-wide roles) must stay: a whereHas on
            // `center` alone would drop every agency-level user.
            ->where(function (Builder $query) {
                $query->whereNull('center_id')
                    ->orWhereHas('center', fn ($centerQuery) => $centerQuery->excludingHidden(
                        Centers::alwaysHiddenCenters()
                    ));
            })
            // A center-scoped actor only ever sees their own center's accounts,
            // and a PCC-level actor only its own agency's.
            ->when($request->user()->isCenterScoped(), function ($query) use ($request) {
                $query->where('center_id', $request->user()->center_id);
            })
            ->when($request->user()->isPccLevel() && $request->user()->agency_id !== null, function ($query) use ($request) {
                $query->where('agency_id', $request->user()->agency_id);
            })
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('center_id'),
                AllowedFilter::exact('agency_id'),
                // Roles live on the permission pivot rather than a users column,
                // so this needs a callback rather than an exact column match.
                AllowedFilter::callback('role', fn ($query, $value) => $query->whereHas(
                    'roles',
                    fn ($roleQuery) => $roleQuery->where('name', $value)
                )),
            ])
            ->allowedSorts(['name', 'email', 'username', 'status', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate(10)
            ->appends($request->query());

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'status' => UserStatusEnum::labels(),
                'role' => UserRoleEnum::labels(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'centers' => $this->assignableCenters($request),
            'enums' => [
                'role' => UserRoleEnum::labels(),
                'status' => UserStatusEnum::labels(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $center = $request->resolvedCenter();

        $user = $this->provisioning->create(
            name: $validated['name'],
            email: $validated['email'],
            username: $validated['username'],
            password: $validated['password'],
            role: $request->roleEnum(),
            center: $center,
            // Every account starts pending; a separate approver activates it.
            status: UserStatusEnum::PENDING,
            // A PCC-wide account belongs to the agency provisioning it; a
            // center-scoped one inherits its agency's from the center.
            agency: $center?->agency ?? $this->actorAgency($request),
        );

        return to_route('users.show', $user)
            ->with('success', 'User created. The account is awaiting approval.');
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        $user->load(['roles:id,name', 'center:id,name,code', 'agency:id,name,code', 'approvedBy:id,name']);

        return Inertia::render('Users/Show', [
            'user' => [
                ...$user->toArray(),
                // Set explicitly: Eloquent omits a null belongsTo from toArray(),
                // so the page would have no key to read until the account had
                // been approved once.
                'approvedBy' => $user->approvedBy?->only(['id', 'name']),
            ],
            // The Show page renders the same form as create/edit in view mode, so
            // it needs the same reference data.
            'centers' => $this->assignableCenters($request),
            'enums' => [
                'status' => UserStatusEnum::labels(),
                'role' => UserRoleEnum::labels(),
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['roles:id,name', 'center:id,name,code', 'agency:id,name,code']);

        return Inertia::render('Users/Edit', [
            'user' => $user->toArray(),
            'centers' => $this->assignableCenters($request),
            'enums' => [
                'role' => UserRoleEnum::labels(),
                'status' => UserStatusEnum::labels(),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
        ]);

        // Blank password on an edit means "leave the credentials alone".
        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $role = $request->resolvedRoleEnum();
        $center = $request->resolvedCenter();

        if ($role instanceof UserRoleEnum) {
            $this->provisioning->assertScopeMatchesRole($role, $center);

            // A PCC-level account must not keep the center it was promoted out
            // of. The agency is inherited from that center and left alone.
            if ($role->isPccLevel() && $user->center_id !== null) {
                $user->center()->dissociate();
            }
        }

        $user->save();

        if ($role instanceof UserRoleEnum) {
            $user->syncRoles([$role->value]);
        }

        if ($role instanceof UserRoleEnum && ! $role->isPccLevel() && $center instanceof Center) {
            $user->center()->associate($center);
            $user->save();
        }

        return to_route('users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Deactivate rather than delete: the records this account authored must keep
     * a valid author, and the audit trail must stay readable.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $this->provisioning->deactivate($user);

        return to_route('users.index')
            ->with('success', 'User deactivated successfully.');
    }

    /**
     * Reverse a deactivation. Backed by UserPolicy::restore, so it needs
     * 'update-users' and the same scope as any other edit.
     */
    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('restore', $user);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot reactivate your own account.');
        }

        if ($user->status !== UserStatusEnum::DEACTIVATED) {
            return back()->with('error', 'Only a deactivated account can be reactivated.');
        }

        $this->provisioning->reactivate($user, $request->user());

        return back()->with('success', 'User reactivated successfully.');
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->authorize('approve', $user);

        if (! $this->provisioning->mayDecideOn($request->user(), $user)) {
            return back()->with(
                'error',
                'You created this account, so another approver has to review it.'
            );
        }

        $this->provisioning->approve($user, $request->user());

        return back()->with('success', 'User approved successfully.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->authorize('reject', $user);

        if (! $this->provisioning->mayDecideOn($request->user(), $user)) {
            return back()->with(
                'error',
                'You created this account, so another approver has to review it.'
            );
        }

        $this->provisioning->reject($user, $request->user());

        return back()->with('success', 'User rejected.');
    }

    /**
     * The agency of the signed-in actor, used to scope PCC-wide accounts.
     */
    private function actorAgency(Request $request): ?Agency
    {
        $agencyId = $request->user()?->agency_id;

        return $agencyId === null ? null : Agency::find($agencyId);
    }

    /**
     * The centers this actor may attach an account to.
     *
     * NHQ is excluded through Centers::alwaysHiddenCenters() — this dropdown is
     * not program-scoped, so GP and NIZ must stay, only the always-hidden set
     * goes.
     */
    private function assignableCenters(Request $request)
    {
        if ($request->user()->isCenterScoped()) {
            return Center::query()
                ->excludingHidden(Centers::alwaysHiddenCenters())
                ->whereKey($request->user()->center_id)
                ->get(['id', 'name', 'code']);
        }

        return Center::query()
            ->excludingHidden(Centers::alwaysHiddenCenters())
            ->when(
                $request->user()->agency_id !== null,
                fn ($query) => $query->where('agency_id', $request->user()->agency_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
