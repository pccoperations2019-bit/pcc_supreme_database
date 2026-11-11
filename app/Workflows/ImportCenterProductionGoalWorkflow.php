<?php

namespace App\Workflows;

use App\Concerns\HasQueueMetadata;
use App\Imports\CenterProductionGoalsImport;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Reader;

class ImportCenterProductionGoalWorkflow
{
    use HasQueueMetadata;

    public function resolve(string $taggableType, ?int $taggableId): void
    {
        if (
            $taggableType !== Str::of(class_basename(Document::class))->lower()->toString()
            || ! $taggableId
        ) {
            return;
        }

        $document = Document::find($taggableId);
        if (! $document || ! $document->file) {
            return;
        }

        $centerIds = DB::table('centers')->pluck('id', 'code')->toArray();

        $sheetNames = $this->resolveGoalSheetNames(
            Storage::disk('dpm')->path($document->file->path)
        );

        $import = new CenterProductionGoalsImport;
        $import->setCenterIds($centerIds);
        $import->setSheetNames($sheetNames);
        $import->onlySheets($sheetNames);
        $import->setDocumentId($taggableId);

        $this->setQueueMetadata($taggableId, 'import');

        Excel::import($import, $document->file->path, 'dpm');
    }

    /**
     * Detect goal sheets by name without loading the whole file.
     *
     * @return array<int, string>
     */
    protected function resolveGoalSheetNames(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        /** @var Reader $reader */
        $reader = IOFactory::createReader(IOFactory::identify($path));

        if (! method_exists($reader, 'listWorksheetNames')) {
            return [];
        }

        return array_values(array_filter(
            $reader->listWorksheetNames($path),
            fn (string $name) => preg_match('/\bGoals$/i', $name) === 1
        ));
    }
}
