<?php

namespace App\Imports;

use App\Actions\Project\UpsertProjectAction;
use App\Enums\ProjectProgramEnum;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class CBEDCenterProjectImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithHeadingRow
{
    protected array $centerIds = [];

    public function setCenterIds(array $centerIds)
    {
        $this->centerIds = $centerIds;
    }

    public function onRow(Row $row)
    {
        $data = $row->toCollection(
            calculateFormulas: true
        );

        if (empty($data->get('name'))) {
            return;
        }

        $projectInvolvement = null;
        if ($data->get('project_involvement')) {
            $involvementData = $data->get('project_involvement');
            if (is_string($involvementData)) {
                $projectInvolvement = array_map('trim', explode(',', $involvementData));
            }
        }

        resolve(UpsertProjectAction::class)->execute(
            name: $data->get('name'),
            program: ProjectProgramEnum::tryFroMatches($data->get('program')) ?? ProjectProgramEnum::DEFAULT,
            isFunded: strtolower($data->get('funded')) === 'yes',
            description: $data->get('description'),
            projectInvolvement: $projectInvolvement,
        );
    }
}
