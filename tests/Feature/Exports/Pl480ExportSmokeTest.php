<?php

namespace Tests\Feature\Exports;

use App\Enums\DocumentStatusEnum;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Users\InteractsWithUsers;
use Tests\TestCase;

class Pl480ExportSmokeTest extends TestCase
{
    use InteractsWithUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_pl480_export_queues_and_completes(): void
    {
        $admin = $this->pccAdmin($this->agency());

        $response = $this->actingAs($admin)->post('/pl480/export', [
            'format' => 'xlsx',
            'queue_export' => true,
            'without_years' => [2024, 2025],
            'with_years' => [2026],
        ]);

        $response->assertOk()->assertJsonStructure(['document_id']);

        $document = Document::with('file')->findOrFail($response->json('document_id'));

        $this->assertSame(DocumentStatusEnum::PROCESSED, $document->status);
        $this->assertNotNull($document->file);
        $this->assertGreaterThan(0, $document->file->size);
    }

    public function test_pl480_export_csv_queues_and_completes(): void
    {
        $admin = $this->pccAdmin($this->agency());

        $response = $this->actingAs($admin)->post('/pl480/export', [
            'format' => 'csv',
            'queue_export' => true,
            'without_years' => [2024, 2025],
            'with_years' => [2026],
        ]);

        $response->assertOk()->assertJsonStructure(['document_id']);

        $document = Document::with('file')->findOrFail($response->json('document_id'));

        $this->assertSame(DocumentStatusEnum::PROCESSED, $document->status);
        $this->assertGreaterThan(0, $document->file->size);
    }
}
