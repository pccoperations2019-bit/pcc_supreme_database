<?php

namespace Tests\Feature\Audits;

use App\Actions\Audit\DescribeAuditActivityAction;
use App\Enums\AuditActionEnum;
use App\Enums\AuditCategoryEnum;
use App\Enums\DocumentStatusEnum;
use App\Enums\UserRoleEnum;
use App\Events\EntityTagged;
use App\Listeners\HandleEntityTagged;
use App\Models\Agency;
use App\Models\Center;
use App\Models\Document;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Users\InteractsWithUsers;
use Tests\TestCase;

/**
 * A generated report must read as an export, not an import.
 *
 * The Generate Report page tags its document `document=processing` alongside
 * `report_type`, and the `document` tag alone routes to the import workflow.
 * Without the export check that tag logged "Imported" for a file that was
 * generated, and ran the import workflow over it.
 */
class ExportAuditTrailTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    private Agency $agency;

    private Center $center;

    private User $pccAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        $this->agency = Agency::create(['code' => 'PCC', 'name' => 'Philippine Carabao Center']);
        $this->center = Center::create([
            'agency_id' => $this->agency->id,
            'code' => 'CLSU',
            'name' => 'CLSU Center',
        ]);
        $this->pccAdmin = $this->user('pcc-admin', UserRoleEnum::AGENCY_ADMIN->value);
    }

    public function test_a_generated_report_logs_exported_not_imported(): void
    {
        $document = $this->exportDocument('Date Annual 2026 10 05');

        // Both events fire for an export: the `document` one must not import
        // or log, the `report_type` one writes the single "Exported" row.
        (new HandleEntityTagged)->handle(new EntityTagged(
            tagKey: 'document',
            tagValue: 'processing',
            taggableType: 'document',
            taggableId: $document->id,
        ));
        (new HandleEntityTagged)->handle(new EntityTagged(
            tagKey: 'report_type',
            tagValue: 'bdp',
            taggableType: 'document',
            taggableId: $document->id,
        ));

        $descriptions = Activity::query()
            ->where('subject_type', 'document')
            ->pluck('description')
            ->all();

        $this->assertContains('Date Annual 2026 10 05 Exported', $descriptions);
        $this->assertNotContains('Date Annual 2026 10 05 Imported', $descriptions);
        $this->assertSame(1, collect($descriptions)->filter(
            fn ($description) => str_contains($description, 'Exported')
        )->count());
    }

    public function test_an_export_entry_reads_under_reports_as_exported(): void
    {
        $document = $this->exportDocument('Date Annual 2026 10 05');

        (new HandleEntityTagged)->handle(new EntityTagged(
            tagKey: 'report_type',
            tagValue: 'bdp',
            taggableType: 'document',
            taggableId: $document->id,
        ));

        $activity = Activity::query()
            ->where('subject_type', 'document')
            ->where('event', AuditActionEnum::EXPORTED->value)
            ->latest('id')
            ->firstOrFail();

        // The queue generated the file, but the report belongs to the admin
        // who asked for it - unlike the system-attributed processing rows.
        $this->assertTrue($activity->causer->is($this->pccAdmin));

        $described = app(DescribeAuditActivityAction::class)->execute($activity);

        $this->assertSame(AuditCategoryEnum::REPORTS->value, $described['category']);
        $this->assertSame(AuditActionEnum::EXPORTED->value, $described['action']);
    }

    public function test_a_template_import_with_a_center_tag_logs_once(): void
    {
        Event::fake([EntityTagged::class]);
        $document = $this->importDocument('goals.xlsx', [
            'template' => 'cbin_ccdp_master_list',
            'center' => 'CLSU',
        ]);

        Excel::shouldReceive('import')->once()->andReturnNull();

        (new HandleEntityTagged)->handle(new EntityTagged('template', 'cbin_ccdp_master_list', 'document', $document->id));
        (new HandleEntityTagged)->handle(new EntityTagged('center', 'CLSU', 'document', $document->id));

        $this->assertSame(['goals.xlsx Imported'], $this->importedDescriptions($document));
    }

    public function test_a_document_import_with_a_center_tag_logs_once(): void
    {
        Event::fake([EntityTagged::class]);
        $document = $this->importDocument('registration.xlsx', [
            'document' => 'business_registration',
            'center' => 'CLSU',
        ]);

        Excel::shouldReceive('import')->once()->andReturnNull();

        (new HandleEntityTagged)->handle(new EntityTagged('center', 'CLSU', 'document', $document->id));
        (new HandleEntityTagged)->handle(new EntityTagged('document', 'business_registration', 'document', $document->id));

        $this->assertSame(['registration.xlsx Imported'], $this->importedDescriptions($document));
    }

    private function importedDescriptions(Document $document): array
    {
        return Activity::query()
            ->where('subject_type', 'document')
            ->where('subject_id', $document->id)
            ->where('event', AuditActionEnum::IMPORTED->value)
            ->pluck('description')
            ->all();
    }

    private function importDocument(string $title, array $tags): Document
    {
        Storage::fake('dpm');
        Storage::disk('dpm')->put('files/import.xlsx', 'spreadsheet-bytes');

        $file = new File([
            'name' => 'import.xlsx',
            'path' => 'files/import.xlsx',
            'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 17,
        ]);
        $file->uploadedBy()->associate($this->pccAdmin);
        $file->save();

        $document = new Document(['title' => $title, 'status' => DocumentStatusEnum::APPROVED]);
        $document->file()->associate($file);
        $document->save();

        foreach ($tags as $key => $value) {
            $document->tags()->create(['key' => $key, 'value' => $value]);
        }

        return $document;
    }

    /**
     * A document tagged the way the Generate Report page tags its exports.
     */
    private function exportDocument(string $title): Document
    {
        $file = new File([
            'name' => 'report.xlsx',
            'path' => 'exports/report.xlsx',
            'type' => 'application/octet-stream',
            'size' => 0,
        ]);
        $file->uploadedBy()->associate($this->pccAdmin);
        $file->save();

        $document = new Document([
            'title' => $title,
            'status' => DocumentStatusEnum::PROCESSING,
        ]);
        $document->file()->associate($file);
        $document->save();

        $document->tags()->create(['key' => 'document', 'value' => 'processing']);
        $document->tags()->create(['key' => 'report_type', 'value' => 'bdp']);

        return $document;
    }

    private function user(string $username, string $role): User
    {
        $user = User::create([
            'agency_id' => $this->agency->id,
            'center_id' => null,
            'name' => $username,
            'username' => $username,
            'email' => $username.'@pcc.gov.ph',
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
