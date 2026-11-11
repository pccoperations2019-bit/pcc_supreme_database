# Actions Guidelines

Purpose
- Encapsulate a single application use case (write or read) behind a small, testable class callable from controllers, console commands, jobs, and listeners.
- Enforce consistency for side effects (DB writes, events, jobs) and improve reuse.

Scope and responsibilities
- One action = one intent (CreateCenter, UpsertCenter, SyncPartnerProjects).
- No validation inside actions. Assume sanitized input (validated earlier via Form Requests, DTOs, or upstream services).
- Prefer returning domain results (Eloquent model, DTO, or primitive) rather than arrays.

Location and naming
- Folder: app/Actions/{Domain}/{ActionName}Action.php
  - Examples: app/Actions/Center/UpsertCenterAction.php, app/Actions/Partner/SyncPartnerAddressesAction.php
- Class name: Verb + Noun + Action suffix (Create..., Update..., Upsert..., Sync..., Import..., Delete..., Attach..., Detach..., Resolve..., Recalculate..., Generate...).
- Method: public function execute(...) strictly typed.

Input rules
- Enumerate parameters. No associative arrays. Use scalar and explicit types (string, int, float, bool, DateTimeInterface, Model types, DTOs).
- Keep constructor DI minimal. Prefer explicit parameters to execute over hidden state.

Output rules
- Return the primary domain result (Model, DTO, Collection, bool, int).
- Never return Response objects from actions (controllers handle HTTP).

Transactions
- Use DB::transaction only when multiple writes must succeed/fail together.
- Do not wrap single-table write operations (e.g., a single updateOrCreate/upsert) unless coupled with other writes or side effects.
- Keep the transaction block minimal; avoid long-running I/O inside transactions (HTTP calls, queues, heavy computation).

Concurrency and idempotency
- Rely on database constraints (unique indexes, FKs) to prevent duplicates.
- Prefer updateOrCreate/firstOrCreate for idempotent writes.
- Consider optimistic checks (whereColumn, version columns) for counters or aggregates.

Error handling
- Let domain errors bubble as exceptions. Do not catch-and-suppress in actions.
- Throw domain-specific exceptions only when needed to express business rules (not validation).

Side effects
- Emit events, dispatch jobs, and update caches inside the action if they are part of the use case.
- Keep side effects after the successful DB commit (use after-commit queue or manual commit boundary).

Testing
- Unit-test actions in isolation. Use database transactions in tests for cleanup.
- Test happy path, constraint violations, and concurrency-sensitive behavior.
- Mock external services at the action boundary.

Usage patterns
- Controller: inject the action and call execute(...).
- Console/Jobs: resolve via container or constructor injection.
- Do not mix request objects into actions.

Examples

Basic write (no transaction)
````php
<?php

namespace App\Actions\Center;

use App\Models\Center;

class UpsertCenterAction
{
    /**
     * Enumerate the params
     */
    public function execute(
        string $code,
        string $name,
        ?string $description = null,
    ): Center {
        // Single-table atomic write; no explicit DB::transaction needed
        return Center::updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'description' => $description]
        );
    }
}
````

Multi-write with transaction
````php
<?php

namespace App\Actions\Center;

use App\Models\Address;
use App\Models\Center;
use Illuminate\Support\Facades\DB;

class UpsertCenterWithAddressAction
{
    public function execute(
        string $code,
        string $name,
        ?string $description,
        // address fields (polymorphic)
        ?string $street,
        ?string $barangay,
        ?string $city,
        ?string $province,
        ?string $region,
        ?string $district,
        ?string $zipCode,
        ?float $latitude,
        ?float $longitude,
        ?string $markerIcon,
    ): Center {
        return DB::transaction(function () use (
            $code, $name, $description,
            $street, $barangay, $city, $province, $region, $district, $zipCode,
            $latitude, $longitude, $markerIcon
        ) {
            $center = Center::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $description]
            );
            
            $center->address()->updateOrCreate(
                [],
                [
                    'street' => $street,
                    'barangay' => $barangay,
                    'city' => $city,
                    'province' => $province,
                    'region' => $region,
                    'district' => $district,
                    'zip_code' => $zipCode,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'marker_icon' => $markerIcon,
                ]
            );

            return $center;
        });
    }
}
````

Read-only query action
````php
<?php

namespace App\Actions\Center;

use App\Models\Center;
use Illuminate\Support\Collection;

class ListCentersForAgencyAction
{
    public function execute(int $agencyId): Collection
    {
        return Center::query()
            ->where('agency_id', $agencyId)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'slug']);
    }
}
````

From a controller
````php
<?php

namespace App\Http\Controllers;

use App\Actions\Center\UpsertCenterAction;
use Illuminate\Http\Request;

class CenterController extends Controller
{
    public function store(Request $request, UpsertCenterAction $upsertCenter)
    {
        // Validation happens here (FormRequest recommended)
        $center = $upsertCenter->execute(
            $request->string('code'),
            $request->string('name'),
            $request->input('description')
        );

        return redirect()->route('centers.show', $center);
    }
}
````

Checklist before merging an action
- Name expresses a single intent.
- Parameters are enumerated and strictly typed.
- No validation inside the action.
- Transaction only if multiple writes/side effects.
- Returns a clear domain result.
- Covered by unit/integration tests.
- Respects DB constraints and polymorphic relationships.
- No HTTP-layer concerns (no Request/Response).

Notes for this codebase
- Centers are identified by a unique code. Ensure a unique index on centers.code for safe updateOrCreate under concurrency.
- Addresses use polymorphic relation (addressable_id/addressable_type). Do not add centers.address_id FKs; manage via morphOne/morphMany.
- Multi-tenant concerns (agency_id, center scoping) should be enforced by callers or injected context, not guessed inside actions.