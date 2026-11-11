<?php

namespace Tests\Feature\CenterAdmin;

use App\Enums\DocumentStatusEnum;
use App\Enums\UserRoleEnum;
use App\Events\EntityTagged;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Document;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Center admins can upload documents for their own center.
 *
 * Documents have no center_id: they reference a center through a polymorphic tag
 * holding the center *code*. Uploads are pinned to the uploader's center, the
 * listing is scoped to it, and editing/deleting stays an agency-level job.
 */
class CenterAdminDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private Center $ownCenter;

    private Center $otherCenter;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRoleEnum::CENTER_ADMIN->value, 'web');

        // Uploading fires EntityTagged, whose listener registers a shutdown
        // handler that logs at PHP shutdown - fatal once the test container is
        // gone. These tests cover center scoping, not the import workflows.
        Event::fake([EntityTagged::class]);

        $agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->ownCenter = $this->center($agency, 'CLSU');
        $this->otherCenter = $this->center($agency, 'MMSU');

        $this->admin = User::factory()->create([
            'agency_id' => $agency->id,
            'center_id' => $this->ownCenter->id,
            'username' => 'admin-clsu',
            'password' => Hash::make('password'),
        ]);
        $this->admin->assignRole(UserRoleEnum::CENTER_ADMIN->value);
    }

    public function test_center_admin_can_open_the_document_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('documents.index'))
            ->assertOk();
    }

    public function test_center_admin_can_open_the_document_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('documents.create'))
            ->assertOk();
    }

    public function test_uploaded_document_is_tagged_with_the_admins_own_center(): void
    {
        $this->actingAs($this->admin)
            ->post(route('documents.store'), $this->payload([
                'tags' => [['key' => 'document', 'value' => 'business_registration']],
            ]))
            ->assertSessionHasNoErrors();

        $document = Document::query()->where('title', 'Registration')->firstOrFail();

        $this->assertTrue(
            $document->tags->contains(fn ($tag) => $tag->key === 'center' && $tag->value === 'CLSU')
        );
    }

    public function test_uploaded_document_cannot_be_tagged_to_another_center(): void
    {
        $this->actingAs($this->admin)
            ->post(route('documents.store'), $this->payload([
                'tags' => [['key' => 'center', 'value' => 'MMSU']],
            ]))
            ->assertSessionHasNoErrors();

        $document = Document::query()->where('title', 'Registration')->firstOrFail();

        $centerTags = $document->tags->where('key', 'center');

        $this->assertCount(1, $centerTags);
        $this->assertSame('CLSU', $centerTags->first()->value);
    }

    public function test_index_only_lists_documents_belonging_to_the_admins_center(): void
    {
        $own = $this->documentTagged('center', 'CLSU');
        $other = $this->documentTagged('center', 'MMSU');

        $response = $this->actingAs($this->admin)
            ->get(route('documents.index'))
            ->assertOk();

        $titles = collect($response->viewData('page')['props']['documents']['data'])
            ->pluck('title')
            ->all();

        $this->assertContains($own->title, $titles);
        $this->assertNotContains($other->title, $titles);
    }

    public function test_index_includes_documents_reachable_through_the_centers_own_farmer(): void
    {
        $farmer = Farmer::create([
            'code' => 'FARMER-00001',
            'first_name' => 'Jason',
            'last_name' => 'Perez',
            'center_id' => $this->ownCenter->id,
        ]);

        $document = $this->documentTagged('farmer', $farmer->code);

        $response = $this->actingAs($this->admin)
            ->get(route('documents.index'))
            ->assertOk();

        $titles = collect($response->viewData('page')['props']['documents']['data'])
            ->pluck('title')
            ->all();

        $this->assertContains($document->title, $titles);
    }

    public function test_center_admin_cannot_view_a_document_from_another_center(): void
    {
        $document = $this->documentTagged('center', 'MMSU');

        $this->actingAs($this->admin)
            ->get(route('documents.show', $document))
            ->assertNotFound();
    }

    public function test_center_admin_can_edit_their_own_draft_but_cannot_delete_it(): void
    {
        $document = $this->documentTagged('center', 'CLSU');

        // A draft is still the uploader's to correct.
        $this->actingAs($this->admin)
            ->get(route('documents.edit', $document))
            ->assertOk();

        // Deleting the document (and its file) stays an agency-level job.
        $this->actingAs($this->admin)
            ->delete(route('documents.destroy', $document))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_center_admin_cannot_edit_a_document_awaiting_review(): void
    {
        $document = $this->documentTagged('center', 'CLSU');
        $document->update(['status' => DocumentStatusEnum::PENDING_REVIEW]);

        $this->actingAs($this->admin)
            ->get(route('documents.edit', $document))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put(route('documents.update', $document), $this->payload())
            ->assertForbidden();
    }

    public function test_center_admin_cannot_edit_a_document_from_another_center(): void
    {
        $document = $this->documentTagged('center', 'MMSU');

        $this->actingAs($this->admin)
            ->get(route('documents.edit', $document))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Registration',
            'file_name' => 'registration.pdf',
            'file_key' => 'documents/registration.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 1024,
        ], $overrides);
    }

    private function documentTagged(string $key, string $value): Document
    {
        // file_id is guarded on Document, so it cannot be mass-assigned.
        $document = Document::forceCreate([
            'title' => "Doc {$key} {$value}",
            'file_id' => $this->makeFileId(),
        ]);

        $document->tags()->create([
            'key' => $key,
            'value' => $value,
            'taggable_type' => 'document',
        ]);

        return $document;
    }

    /**
     * FileFactory hardcodes uploaded_by => 1, which breaks under
     * RefreshDatabase, so insert the row directly.
     */
    private function makeFileId(): int
    {
        return (int) DB::table('files')->insertGetId([
            'name' => 'test.pdf',
            'path' => 'documents/test.pdf',
            'type' => 'application/pdf',
            'size' => 4,
            'uploaded_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function center(Agency $agency, string $code): Center
    {
        return Center::create([
            'agency_id' => $agency->id,
            'code' => $code,
            'name' => strtoupper($code).' Center',
        ]);
    }
}
