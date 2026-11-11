<?php

namespace App\Imports;

use App\Actions\ManureProduction\UpsertManureProductionAction;
use App\Enums\MonthEnum;
use App\Enums\ProgramEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Row;

class ManureProductionSheetImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithChunkReading, WithEvents, WithHeadingRow
{
    private UpsertManureProductionAction $upsertManureProductionAction;

    private array $centerIdsByCode = [];

    private array $centerIdsByName = [];

    private ProgramEnum $program = ProgramEnum::DEFAULT;

    public function __construct(?UpsertManureProductionAction $upsertManureProductionAction = null)
    {
        $this->upsertManureProductionAction = $upsertManureProductionAction ?? resolve(UpsertManureProductionAction::class);
    }

    public function setProgram(ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function setDefaultYear(?int $year): void {}

    public function chunkSize(): int
    {
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheetName = $event->sheet->getTitle();
            },
        ];
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row): void
    {
        $rowData = $row->toArray(calculateFormulas: true);

        $centerName = $this->getRowValue($rowData, ['center', 'Center']);
        $type = $this->getRowValue($rowData, ['type', 'Type']);
        $rowYear = $this->getRowValue($rowData, ['year', 'Year', 'YEAR']);

        $rowYear = $rowYear !== null && $rowYear !== '' ? (int) $rowYear : null;
        $centerName = $centerName !== null ? trim((string) $centerName) : null;
        $centerName = $centerName === '' ? null : $centerName;

        if (! $centerName || ! $rowYear) {
            return;
        }

        $centerId = $this->resolveCenter($centerName, $row->getIndex());

        if (! $centerId) {
            return;
        }

        // Determine program from Type column
        $program = $this->program;
        if ($type) {
            $typeLower = strtolower(trim((string) $type));
            if (stripos($typeLower, 'cbed') !== false) {
                $program = ProgramEnum::CBED;
            } elseif (stripos($typeLower, 'institutional') !== false || stripos($typeLower, 'ih') !== false) {
                $program = ProgramEnum::IH;
            }
        }

        foreach (MonthEnum::cases() as $monthEnum) {
            $monthColumn = strtoupper(substr($monthEnum->name, 0, 3));
            $value = $this->getRowValue($rowData, [$monthColumn, $monthEnum->name]);
            $monthValue = ($value !== null && $value !== '') ? (float) $value : 0;

            if ($monthValue === 0.0) {
                continue;
            }

            $monthNumber = $monthEnum->value;

            try {
                $this->upsertManureProductionAction->execute(
                    centerId: $centerId,
                    partnerId: null,
                    farmerId: null,
                    year: $rowYear,
                    program: $program,
                    totalVolume: $monthValue,
                    monthlyData: [strtolower($monthColumn) => $monthValue],
                    month: $monthNumber,
                    quarter: null
                );
            } catch (\Exception $e) {
                Log::error('ManureProductionSheetImport: Error processing row', [
                    'row' => $row->getIndex(),
                    'error' => $e->getMessage(),
                    'data' => $rowData,
                ]);
            }
        }
    }

    private function getRowValue(array $rowData, array $keys, $default = null)
    {
        $rowDataLower = array_change_key_case($rowData, CASE_LOWER);

        foreach ($keys as $key) {
            $keyLower = strtolower($key);
            if (isset($rowDataLower[$keyLower]) && $rowDataLower[$keyLower] !== null && $rowDataLower[$keyLower] !== '') {
                return $rowDataLower[$keyLower];
            }
        }

        return $default;
    }

    private function resolveCenter(?string $centerName, int $rowIndex): ?int
    {
        if (! $this->centerIdsByCode && ! $this->centerIdsByName) {
            $centers = DB::table('centers')->select('id', 'code', 'name')->get();
            foreach ($centers as $center) {
                if (! empty($center->code)) {
                    $this->centerIdsByCode[strtoupper(trim((string) $center->code))] = (int) $center->id;
                }
                if (! empty($center->name)) {
                    $this->centerIdsByName[strtolower(trim((string) $center->name))] = (int) $center->id;
                }
            }
        }

        $centerId = null;
        if ($centerName) {
            $centerId = $this->centerIdsByCode[strtoupper(trim($centerName))]
                ?? $this->centerIdsByName[strtolower(trim($centerName))]
                ?? null;
        }

        if (! $centerId) {
            Log::warning('ManureProductionSheetImport: Center not resolved', [
                'row' => $rowIndex,
                'center_value' => $centerName,
            ]);
        }

        return $centerId;
    }
}
