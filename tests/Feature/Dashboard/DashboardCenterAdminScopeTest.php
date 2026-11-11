<?php

namespace Tests\Feature\Dashboard;

use App\Enums\DocumentEnum;
use App\Enums\ProgramEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Document;
use App\Models\Partner;
use App\Models\User;
use App\Queries\Dashboard\DocumentCountQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardCenterAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    private function center(string $code): Center
    {
        return Center::create([
            'agency_id' => $this->agency->id,
            'code' => $code,
            'name' => strtoupper($code).' Center',
        ]);
    }

    private function centerAdmin(Center $center): User
    {
        $user = User::factory()->create([
            'agency_id' => $this->agency->id,
            'center_id' => $center->id,
            'username' => 'admin-'.$center->code,
            'password' => Hash::make('password'),
        ]);
        $user->assignRole(UserRoleEnum::CENTER_ADMIN->value);

        return $user;
    }

    /**
     * FileFactory hardcodes uploaded_by => 1, which breaks under RefreshDatabase,
     * so insert the row directly.
     */
    private function makeFileId(): int
    {
        return (int) DB::table('files')->insertGetId([
            'name' => 'test.txt',
            'path' => 'files/test.txt',
            'type' => 'text/plain',
            'size' => 4,
            'uploaded_by' => $this->uploader->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tagDocumentAsPartner(DocumentEnum $type, Partner $partner): void
    {
        // file_id is not mass-assignable on Document.
        $document = Document::forceCreate([
            'title' => $type->name,
            'file_id' => $this->makeFileId(),
        ]);
        $document->tags()->createMany([
            ['key' => 'document', 'value' => $type->value],
            ['key' => 'partner', 'value' => $partner->code],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRoleEnum::CENTER_ADMIN->value, 'web');
        Role::findOrCreate(UserRoleEnum::AGENCY_ADMIN->value, 'web');

        // CenterFactory is stale against the current schema.
        $this->agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->uploader = User::factory()->create(['username' => 'uploader']);
    }

    private $agency;

    private $uploader;

    public function test_cbed_compliance_counts_are_scoped_to_the_center(): void
    {
        $mine = $this->center('MINE');
        $theirs = $this->center('THEIRS');

        $myPartner = Partner::create(['center_id' => $mine->id, 'code' => 'MINE-P1', 'name' => 'Mine']);
        $theirPartner = Partner::create(['center_id' => $theirs->id, 'code' => 'THEIRS-P1', 'name' => 'Theirs']);

        $this->tagDocumentAsPartner(DocumentEnum::CSO, $myPartner);
        $this->tagDocumentAsPartner(DocumentEnum::CSO, $theirPartner);
        $this->tagDocumentAsPartner(DocumentEnum::FDA_LTO, $theirPartner);

        $counts = resolve(DocumentCountQuery::class)->execute($mine->id, ProgramEnum::CBED);

        $this->assertSame(1, $counts['cso_compliance'], 'Should only count this center CSO documents');
        $this->assertSame(0, $counts['fda_lto_compliance'], 'Must not count another center documents');
    }

    public function test_global_compliance_counts_are_unchanged_without_a_center(): void
    {
        $mine = $this->center('MINE');
        $theirs = $this->center('THEIRS');
        $myPartner = Partner::create(['center_id' => $mine->id, 'code' => 'MINE-P1', 'name' => 'Mine']);
        $theirPartner = Partner::create(['center_id' => $theirs->id, 'code' => 'THEIRS-P1', 'name' => 'Theirs']);

        $this->tagDocumentAsPartner(DocumentEnum::CSO, $myPartner);
        $this->tagDocumentAsPartner(DocumentEnum::CSO, $theirPartner);

        $counts = resolve(DocumentCountQuery::class)->execute(null, ProgramEnum::CBED);

        $this->assertSame(2, $counts['cso_compliance']);
    }

    public function test_ih_compliance_counts_remain_scoped_by_center_code(): void
    {
        $mine = $this->center('MINE');
        $theirs = $this->center('THEIRS');

        foreach ([$mine, $theirs] as $center) {
            $document = Document::forceCreate([
                'title' => 'cso '.$center->code,
                'file_id' => $this->makeFileId(),
            ]);
            $document->tags()->createMany([
                ['key' => 'document', 'value' => DocumentEnum::CSO->value],
                ['key' => 'center', 'value' => $center->code],
            ]);
        }

        $counts = resolve(DocumentCountQuery::class)->execute($mine->id, ProgramEnum::IH);

        $this->assertSame(1, $counts['cso_compliance']);
    }

    public function test_dashboard_centers_payload_contains_only_the_own_center(): void
    {
        $mine = $this->center('MINE');
        $this->center('THEIRS');
        $admin = $this->centerAdmin($mine);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('centers', fn ($centers) => count($centers) === 1
                    && $centers[0]['code'] === 'MINE')
            );
    }

    public function test_agency_admin_still_sees_every_center(): void
    {
        $this->center('MINE');
        $this->center('THEIRS');

        $admin = User::factory()->create([
            'agency_id' => $this->agency->id,
            'username' => 'agency-admin',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole(UserRoleEnum::AGENCY_ADMIN->value);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('centers', fn ($centers) => count($centers) === 2)
            );
    }
}
