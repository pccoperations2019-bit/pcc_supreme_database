---
applyTo: "database/**/*.php"
---

# Database Rules: Migrations, Factories, Seeders
Environment-aware standards for local/dev, testing, and production.

## Migration
- Forward-only, additive-first. Never drop/rename columns in the same release that adds/uses them.
- Idempotent: guard with Schema::hasTable/hasColumn when altering existing schema.
- Zero-downtime minded for MySQL 8:
  - Add nullable columns, backfill via jobs/commands, then enforce NOT NULL in a later migration.
  - Add indexes explicitly and name them; avoid heavy ALTERs during peak traffic.
- Don’ts: no data seeding in migrations, no migrate:fresh outside dev/test.
- Conventions:
  - Tables: snake_case plural; pivots singular_singular (alphabetical).
  - PK: $table->id(); timestamps: $table->timestamps();
  - FKs: use foreignId()->constrained()->cascadeOnDelete() where appropriate.
  - Multitenancy: use agency_id and center_id; ensure proper indexes.

Example (safe alter with guards):
```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('agencies')) {
            Schema::create('agencies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('centers') && !Schema::hasColumn('centers', 'agency_id')) {
            Schema::table('centers', function (Blueprint $table) {
                $table->foreignId('agency_id')
                    ->after('id')
                    ->constrained('agencies')
                    ->cascadeOnDelete()
                    ->index();
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'agency_id')) {
                    $table->foreignId('agency_id')->nullable()->after('id')->constrained('agencies')->nullOnDelete()->index();
                }
                if (!Schema::hasColumn('users', 'center_id')) {
                    $table->foreignId('center_id')->nullable()->after('agency_id')->constrained('centers')->nullOnDelete()->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Rollbacks are for dev/test only; avoid in production.
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'center_id')) {
                    $table->dropConstrainedForeignId('center_id');
                }
                if (Schema::hasColumn('users', 'agency_id')) {
                    $table->dropConstrainedForeignId('agency_id');
                }
            });
        }
        if (Schema::hasTable('centers') && Schema::hasColumn('centers', 'agency_id')) {
            Schema::table('centers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('agency_id');
            });
        }
        // Do not drop agencies table automatically to preserve data.
    }
};
```

## Factory
- Deterministic for tests: use small datasets, avoid heavy relations by default. Prefer states for optional relations.
- Relations:
  - Center belongs to Agency.
  - User may be agency-level (with agency_id) or center-level (with center_id and matching agency_id).
- Spatie roles: assign via states/afterCreating; ensure roles exist in test seeds.

Examples:
```php
// AgencyFactory
use Illuminate\Database\Eloquent\Factories\Factory;

class AgencyFactory extends Factory {
    protected $model = \App\Models\Agency::class;
    public function definition(): array {
        return [
            'name' => $this->faker->unique()->company() . ' Agency',
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}

// CenterFactory
use Illuminate\Database\Eloquent\Factories\Factory;

class CenterFactory extends Factory {
    protected $model = \App\Models\Center::class;
    public function definition(): array {
        return [
            'agency_id' => \App\Models\Agency::factory(),
            'name' => $this->faker->unique()->company() . ' Center',
        ];
    }
}

// UserFactory
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory {
    protected $model = \App\Models\User::class;

    public function definition(): array {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'), // override in prod seed via env
        ];
    }

    public function agencyLevel(): self {
        return $this->state(function () {
            $agency = \App\Models\Agency::factory()->create();
            return ['agency_id' => $agency->id, 'center_id' => null];
        })->afterCreating(function (\App\Models\User $user) {
            // Roles must exist (seeded by RolesPermissionsSeeder)
            $user->assignRole('agency-admin');
        });
    }

    public function centerLevel(): self {
        return $this->state(function () {
            $center = \App\Models\Center::factory()->create();
            return ['agency_id' => $center->agency_id, 'center_id' => $center->id];
        })->afterCreating(function (\App\Models\User $user) {
            $user->assignRole('center-admin');
        });
    }
}
```

## Seeder
- Environment policy
  - production: minimal, idempotent, no demo data, no destructive operations. Use --force in CI/CD.
  - local/development: rich demo data guarded by a feature flag (APP_SEED_DEMO=true).
  - testing: tiny, deterministic dataset. Prefer RefreshDatabase with DatabaseSeeder calling TestSeeder.
- Idempotency: use firstOrCreate/upsert; wrap in transactions per seeder; clear Spatie cache after role/permission changes.
- Credentials: never hardcode prod passwords. Read from env (SEED_ADMIN_EMAIL, SEED_ADMIN_PASSWORD).

Structure:
```php
// DatabaseSeeder
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production')) {
            $this->call([
                RolesPermissionsSeeder::class,
                ProductionSeeder::class,
            ]);
            return;
        }

        if (App::environment('testing')) {
            $this->call([
                RolesPermissionsSeeder::class,
                TestSeeder::class,
            ]);
            return;
        }

        // local/development
        $this->call([
            RolesPermissionsSeeder::class,
            DevSeeder::class,
        ]);
    }
}
```

Roles and permissions:
```php
// RolesPermissionsSeeder
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perms = collect([
            'manage-agency', 'view-agency',
            'manage-center', 'view-center',
            'manage-users', 'view-users',
        ])->map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));

        $agencyAdmin = Role::firstOrCreate(['name' => 'agency-admin', 'guard_name' => 'web']);
        $centerAdmin = Role::firstOrCreate(['name' => 'center-admin', 'guard_name' => 'web']);

        $agencyAdmin->syncPermissions(Permission::all());
        $centerAdmin->syncPermissions(Permission::whereIn('name', [
            'view-agency', 'view-center', 'manage-center', 'view-users',
        ])->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

Production seed (minimal, idempotent):
```php
// ProductionSeeder
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\{Agency, Center, User};

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $agency = Agency::firstOrCreate(['name' => 'Philippine Carabao Center'], [
                'description' => 'PCC (Main Agency)',
            ]);

            $mainCenter = Center::firstOrCreate(
                ['name' => 'Main Center', 'agency_id' => $agency->id],
                []
            );

            $email = env('SEED_ADMIN_EMAIL');
            $password = env('SEED_ADMIN_PASSWORD');

            if ($email && $password) {
                $admin = User::firstOrCreate(['email' => $email], [
                    'name' => 'PCC Admin',
                    'password' => Hash::make($password),
                    'agency_id' => $agency->id,
                    'center_id' => null,
                ]);
                if (!$admin->hasRole('agency-admin')) {
                    $admin->assignRole('agency-admin');
                }
            }
        });
    }
}
```

Dev seed (guarded by APP_SEED_DEMO):
```php
// DevSeeder
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\{Agency, Center, User};

class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (!filter_var(env('APP_SEED_DEMO', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->warn('APP_SEED_DEMO=false, skipping demo seeds.');
            return;
        }

        DB::transaction(function () {
            $agency = Agency::factory()->create(['name' => 'Philippine Carabao Center']);

            $main = Center::factory()->create(['agency_id' => $agency->id, 'name' => 'Main Center']);
            $branches = Center::factory()->count(3)->create(['agency_id' => $agency->id]);

            User::factory()->agencyLevel()->create(['email' => 'admin@pcc.test', 'agency_id' => $agency->id]);

            foreach ($branches as $center) {
                User::factory()->centerLevel()->create([
                    'email' => "center+{$center->id}@pcc.test",
                    'center_id' => $center->id,
                    'agency_id' => $agency->id,
                ]);
            }
        });
    }
}
```

Test seed (minimal, deterministic):
```php
// TestSeeder
use Illuminate\Database\Seeder;
use App\Models\{Agency, Center, User};

class TestSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::factory()->create(['name' => 'PCC']);
        $c1 = Center::factory()->create(['agency_id' => $agency->id, 'name' => 'Main Center']);
        $c2 = Center::factory()->create(['agency_id' => $agency->id, 'name' => 'Branch A']);

        User::factory()->agencyLevel()->create(['email' => 'agency@test.local', 'agency_id' => $agency->id]);
        User::factory()->centerLevel()->create(['email' => 'center1@test.local', 'center_id' => $c1->id, 'agency_id' => $agency->id]);
        User::factory()->centerLevel()->create(['email' => 'center2@test.local', 'center_id' => $c2->id, 'agency_id' => $agency->id]);
    }
}
```

Commands
- Development:
  - php artisan migrate:fresh --seed
  - APP_SEED_DEMO=true php artisan db:seed
- Testing:
  - php artisan test (use RefreshDatabase; DatabaseSeeder will route to TestSeeder)
- Production:
  - php artisan migrate --force
  - php artisan db:seed --class=ProductionSeeder --force

Safety
- Never run migrate:fresh or demo seeds in production.
- Use transactions in seeders; avoid truncating tables.
- Clear Spatie permission cache when changing roles/permissions.