<?php

namespace App\Imports;

use App\Actions\Center\UpsertProductionGoalAction;
use App\Actions\Center\UpsertSalesGoalAction;
use App\Enums\ProgramEnum;
use App\Support\Sanitizer;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class CenterGoalsImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithHeadingRow
{
    public $centerIds = [];

    private ?ProgramEnum $program = null;

    private ?int $year = null;

    public function setCenterIds(array $centerIds): void
    {
        $this->centerIds = $centerIds;
    }

    public function setProgram(?ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function setYear(?int $year): void
    {
        $this->year = $year;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row)
    {

        $data = $row->toArray(
            calculateFormulas: true
        );

        $centerCode = Sanitizer::parseString($data['center_code'] ?? null);
        if (! $centerCode || $centerCode === '0') {
            return;
        }

        $centerId = $this->centerIds[$centerCode]
            ?? $this->centerIds[strtoupper($centerCode)]
            ?? $this->centerIds[trim($centerCode)]
            ?? $this->centerIds[strtoupper(trim($centerCode))]
            ?? null;

        if (! $centerId) {
            Log::warning('CenterGoalsImport: Center not resolved', [
                'row' => $row->getIndex(),
                'center_code' => $centerCode,
            ]);

            return;
        }

        $program = $this->program
            ?? ProgramEnum::tryFrom(strtolower(trim((string) ($data['program'] ?? ''))))
            ?? ProgramEnum::IH;

        $year = $this->year
            ?? (is_numeric($data['year']) ? intval($data['year']) : null);

        resolve(UpsertProductionGoalAction::class)->execute(
            centerId: (int) $centerId,
            year: $year,
            program: $program,
            milkProductionGoal: is_numeric($data['milk_production']) ? floatval($data['milk_production']) : null,
            meatMeatEquivalentGoal: is_numeric($data['meat_production']) ? floatval($data['meat_production']) : null,
            manureBasedGoal: is_numeric($data['manure_production']) ? floatval($data['manure_production']) : null,
            hideProductionGoal: is_numeric($data['hide_production_goal'] ?? $data['hide_production'] ?? null)
                ? floatval($data['hide_production_goal'] ?? $data['hide_production'])
                : null
        );

        resolve(UpsertSalesGoalAction::class)->execute(
            centerId: (int) $centerId,
            year: $year,
            program: $program,
            milkSalesGoal: is_numeric($data['milk_sales']) ? floatval($data['milk_sales']) : null,
            meatSalesGoal: is_numeric($data['meat_sales']) ? floatval($data['meat_sales']) : null,
            manureSalesGoal: is_numeric($data['manure_sales']) ? floatval($data['manure_sales']) : null
        );
    }
}
