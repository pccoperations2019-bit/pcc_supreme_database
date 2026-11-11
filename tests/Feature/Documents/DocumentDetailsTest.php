<?php

namespace Tests\Feature\Documents;

use App\Actions\Document\DescribeDocumentAction;
use App\Enums\DocumentStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Document;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An upload's facts are split across the document, its tags and the record a tag
 * points at, so the Documents list has to ask the server to resolve them before
 * a reviewer can tell whether a file was tagged correctly.
 */
class DocumentDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Center $center;

    private User $centerAdmin;

    private User $pccAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(UserRoleEnum::CENTER_ADMIN->value, 'web');
        Role::findOrCreate(UserRoleEnum::AGENCY_ADMIN->value, 'web');

        Storage::fake('dpm');

        $agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->center = Center::create([
            'agency_id' => $agency->id,
            'code' => 'CLSU',
            'name' => 'CLSU Center',
        ]);

        $this->centerAdmin = $this->user('admin-clsu', $agency->id, $this->center->id, UserRoleEnum::CENTER_ADMIN->value);
        $this->pccAdmin = $this->user('pcc-admin', $agency->id, null, UserRoleEnum::AGENCY_ADMIN->value);
    }

    public function test_the_details_endpoint_names_the_document_and_what_it_is_attached_to(): void
    {
        $document = $this->upload([
            ['key' => 'document', 'value' => 'fda-lto'],
            ['key' => 'center', 'value' => 'CLSU'],
        ]);

        $response = $this->actingAs($this->pccAdmin)
            ->getJson(route('xhr.documents.details', $document))
            ->assertOk();

        $details = collect($response->json('details'))->keyBy('label');

        $this->assertSame('FDA-LTO', $details['Document Type']['value'], 'A tag value must read as its label, not its enum value.');
        $this->assertSame('CLSU Center', $details['Attached To']['value'], 'The attachment is named by the record, not by the stored center code.');
        $this->assertStringContainsString('Template - CBIN:CCDP.xlsx', $details['File']['value']);
    }

    public function test_the_details_endpoint_reports_every_tag_with_its_label(): void
    {
        $document = $this->upload([
            ['key' => 'document', 'value' => 'fda-lto'],
            ['key' => 'center', 'value' => 'CLSU'],
        ]);

        $tags = collect(
            $this->actingAs($this->pccAdmin)
                ->getJson(route('xhr.documents.details', $document))
                ->json('tags')
        )->keyBy('key');

        $this->assertSame('Document Type', $tags['document']['label']);
        $this->assertSame('fda-lto', $tags['document']['value']);
        $this->assertSame('FDA-LTO', $tags['document']['label_value']);
        $this->assertSame('Center', $tags['center']['label']);
        $this->assertSame('CLSU Center', $tags['center']['label_value']);
    }

    public function test_an_upload_with_no_type_tag_is_flagged_as_incomplete(): void
    {
        // The store tags a center admin's upload with their center, so this file
        // is attached but still says nothing about what it is.
        $document = $this->upload();

        $missing = $this->actingAs($this->pccAdmin)
            ->getJson(route('xhr.documents.details', $document))
            ->json('missing_tags');

        $this->assertSame(['Type (template, document or report)'], $missing);
    }

    public function test_a_template_import_with_its_center_is_fully_tagged(): void
    {
        // A bulk import names its template and its center - the `document` kind
        // tag belongs to compliance files, not to imports, so its absence here
        // is correct rather than incomplete.
        $document = $this->upload([
            ['key' => 'template', 'value' => 'cbed_master_list'],
            ['key' => 'center', 'value' => 'CLSU'],
        ]);

        $this->assertSame(
            [],
            $this->actingAs($this->pccAdmin)
                ->getJson(route('xhr.documents.details', $document))
                ->json('missing_tags')
        );
    }

    public function test_a_fully_tagged_upload_is_not_flagged(): void
    {
        $document = $this->upload([
            ['key' => 'document', 'value' => 'fda-lto'],
            ['key' => 'center', 'value' => 'CLSU'],
        ]);

        $this->assertSame(
            [],
            $this->actingAs($this->pccAdmin)
                ->getJson(route('xhr.documents.details', $document))
                ->json('missing_tags')
        );
    }

    public function test_the_document_page_carries_the_same_details(): void
    {
        $document = $this->upload([
            ['key' => 'document', 'value' => 'fda-lto'],
            ['key' => 'center', 'value' => 'CLSU'],
        ]);

        $details = $this->actingAs($this->pccAdmin)
            ->get(route('documents.show', $document))
            ->assertOk()
            ->viewData('page')['props']['details'];

        $this->assertSame(
            'CLSU Center',
            collect($details['details'])->firstWhere('label', 'Attached To')['value']
        );
        $this->assertSame([], $details['missing_tags']);
    }

    public function test_a_center_admin_cannot_read_the_details_of_another_centers_document(): void
    {
        $other = Center::create([
            'agency_id' => $this->center->agency_id,
            'code' => 'MARC',
            'name' => 'MARC Center',
        ]);

        $document = $this->upload();
        $document->tags()->updateOrCreate(
            ['key' => 'center'],
            ['value' => $other->code]
        );

        $this->actingAs($this->centerAdmin)
            ->getJson(route('xhr.documents.details', $document))
            ->assertNotFound();
    }

    public function test_the_details_endpoint_requires_authentication(): void
    {
        $file = new File([
            'name' => 'farmers.xlsx',
            'path' => 'files/farmers.xlsx',
            'type' => 'application/vnd.ms-excel',
            'size' => 16,
        ]);
        $file->uploadedBy()->associate($this->centerAdmin);
        $file->save();

        $document = new Document([
            'title' => 'Farmers',
            'status' => DocumentStatusEnum::DRAFT,
        ]);
        $document->file()->associate($file);
        $document->save();

        $this->get(route('xhr.documents.details', $document))->assertRedirect(route('login'));
    }

    public function test_a_generated_export_reports_no_document_type(): void
    {
        // A report workflow's file is not a kind of document. It carries a
        // report_type tag instead, and the `document=processing` tag some of those
        // workflows write is a marker - reading it out as "Document Type:
        // Processing" told a reviewer nothing.
        $document = $this->documentWithTags([
            ['key' => 'document', 'value' => 'processing'],
            ['key' => 'report_type', 'value' => 'milk-production-dashboard'],
        ]);

        $details = app(DescribeDocumentAction::class)->execute($document);

        $this->assertNull($this->detailValue($details['details'], 'Document Type'));
        $this->assertSame(
            ['report_type'],
            array_column($details['tags'], 'key'),
            'The marker tag must not come back as a Document Type badge either.',
        );
    }

    /**
     * @param  list<array{key: string, value: string}>  $tags
     */
    private function documentWithTags(array $tags): Document
    {
        $file = new File([
            'name' => 'milk-production-dashboard.pdf',
            'path' => 'exports/reports/milk-production-dashboard.pdf',
            'type' => 'application/pdf',
            'size' => 16,
        ]);
        $file->uploadedBy()->associate($this->pccAdmin);
        $file->save();

        $document = new Document([
            'title' => 'Milk Production Dashboard',
            'status' => DocumentStatusEnum::PROCESSING,
        ]);
        $document->file()->associate($file);
        $document->save();

        foreach ($tags as $tag) {
            $document->tags()->create($tag);
        }

        return $document->fresh(['tags', 'file']);
    }

    /**
     * @param  list<array{label: string, value: string}>  $details
     */
    private function detailValue(array $details, string $label): ?string
    {
        foreach ($details as $detail) {
            if ($detail['label'] === $label) {
                return $detail['value'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{key: string, value: string}>  $tags
     */
    private function upload(array $tags = []): Document
    {
        $key = 'files/'.fake()->uuid().'/Template - CBIN:CCDP.xlsx';
        Storage::disk('dpm')->put($key, 'spreadsheet-bytes');

        $this->actingAs($this->centerAdmin)
            ->post(route('documents.store'), [
                'title' => 'CBIN/CCDP Project Sites',
                'file_name' => 'Template - CBIN:CCDP.xlsx',
                'file_key' => $key,
                'file_type' => 'application/vnd.ms-excel',
                'file_size' => 16,
                'tags' => $tags,
            ])
            ->assertSessionHasNoErrors();

        return Document::query()->where('title', 'CBIN/CCDP Project Sites')->latest('id')->firstOrFail();
    }

    private function user(string $username, int $agencyId, ?int $centerId, string $role): User
    {
        $user = User::factory()->create([
            'agency_id' => $agencyId,
            'center_id' => $centerId,
            'username' => $username,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
