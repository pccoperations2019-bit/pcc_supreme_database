<?php

namespace App\Imports;

use App\Actions\Service\UpsertServiceAction;
use App\Enums\InterventionTypeEnum;
use App\Enums\ServiceCategoryEnum;
use App\Enums\ServiceProgramEnum;
use App\Enums\ServiceSubCategoryEnum;
use App\Enums\ValueChainSegmentEnum;
use App\Imports\Concerns\WithDocumentStatus;
use App\Models\Service;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class CBEDCenterServicesImport implements OnEachRow, SkipsEmptyRows, WithCalculatedFormulas, WithEvents, WithHeadingRow
{
    use WithDocumentStatus;

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

        $serviceCode = $data->get('service_code');
        if (empty($serviceCode)) {

            $categoryEnum = ServiceCategoryEnum::fromLabel($data->get('category'));
            $subCategoryEnum = ServiceSubCategoryEnum::fromLabel($data->get('sub_category'));

            $serviceCode = Service::generateCode(
                category: $categoryEnum->value,
                subCategory: $subCategoryEnum->value,
            );
        }

        $serviceAction = resolve(UpsertServiceAction::class);
        $serviceAction->execute(
            code: $serviceCode,
            name: $data->get('service_name'),
            program: ServiceProgramEnum::fromLabel($data->get('program')),
            valueChainSegment: ValueChainSegmentEnum::fromLabel($data->get('value_chain_segment')),
            category: ServiceCategoryEnum::fromLabel($data->get('category')),
            subCategory: ServiceSubCategoryEnum::fromLabel($data->get('sub_category')),
            description: $data->get('description'),
            interventionType: InterventionTypeEnum::fromLabel($data->get('intervention_type')),
        );
    }
}
