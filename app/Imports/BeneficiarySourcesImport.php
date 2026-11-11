<?php

namespace App\Imports;

use App\Actions\BeneficiarySource\UpsertBeneficiarySourceAction;
use App\Enums\MilkFeedingProgramFunderEnum;
use App\Imports\Concerns\WithDocumentStatus;
use App\Support\Sanitizer;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class BeneficiarySourcesImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithHeadingRow
{
    use WithDocumentStatus;

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row)
    {
        $rowData = $row->toArray(calculateFormulas: true);

        $getValue = function ($keys, $default = null) use ($rowData) {
            foreach ($keys as $key) {
                if (isset($rowData[$key]) && $rowData[$key] !== '' && $rowData[$key] !== null) {
                    return $rowData[$key];
                }
            }

            return $default;
        };

        $code = trim($getValue(['code', 'Code'], ''));
        $name = trim($getValue(['name', 'Name'], ''));
        $year = (int) Sanitizer::parseNumeric($getValue(['year', 'Year']));
        $amount = (int) Sanitizer::parseNumeric($getValue(['amount', 'Amount']));

        if ($code === '' || $code === '0' || ($name === '' || $name === '0') || $year === 0) {
            return;
        }

        $enumMatch = MilkFeedingProgramFunderEnum::tryFroMatches($code);
        if ($enumMatch instanceof MilkFeedingProgramFunderEnum) {
            $code = $enumMatch->value;
        }

        resolve(UpsertBeneficiarySourceAction::class)->execute(
            code: $code,
            name: $name,
            year: $year,
            amount: $amount
        );
    }
}
