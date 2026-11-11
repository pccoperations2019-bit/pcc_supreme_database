<?php

namespace App\Actions\Service;

use App\Enums\ServiceProgramEnum;
use App\Enums\ValueChainSegmentEnum;
use App\Models\Service;

class UpsertServiceAction
{
    public function execute(
        ?string $code,
        string $name,
        ServiceProgramEnum $program,
        ValueChainSegmentEnum $valueChainSegment,
        ?string $mfoCode,
        ?array $category,
        ?array $subCategory,
        ?array $type,
        ?string $description,
        ?string $interventionType = null,
    ): Service {
        return Service::updateOrCreate(
            [
                'name' => $name,
            ],
            array_filter([
                'simplified_code' => $code,
                'mfo_code' => $mfoCode,
                'program' => $program,
                'value_chain_segment' => $valueChainSegment,
                'category' => $category,
                'sub_category' => $subCategory,
                'type' => $type,
                'description' => $description,
            ], fn ($value) => $value !== null && $value !== '')
        );
    }
}
