<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MilkFeedingProgramMultipleSheetExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected int|array|null $year = null,
        protected int|array|null $centerId = null,
        protected int|array|null $partnerId = null,
        protected string|array|null $fundedBy = null,
        protected string|array|null $region = null,
        protected string|array|null $division = null,
        protected string|array|null $city = null,
        protected string|array|null $milkType = null,
        protected string|array|null $modeOfProcurement = null,
        protected int|array|null $supplier = null,
        protected string|array|null $province = null,
    ) {

        if (! is_array($this->year) && $this->year !== null) {
            $this->year = [$this->year];
        }
    }

    public function sheets(): array
    {
        $sheets = [];

        if (is_array($this->year) && count($this->year) > 0) {
            foreach ($this->year as $year) {
                $sheets[] = new MilkFeedingProgramExport(
                    $year,
                    $this->centerId,
                    $this->partnerId,
                    $this->fundedBy,
                    $this->region,
                    $this->division,
                    $this->city,
                    $this->milkType,
                    $this->modeOfProcurement,
                    $this->supplier,
                    $this->province,
                );
            }
        } else {

            $sheets[] = new MilkFeedingProgramExport(
                null,
                $this->centerId,
                $this->partnerId,
                $this->fundedBy,
                $this->region,
                $this->division,
                $this->city,
                $this->milkType,
                $this->modeOfProcurement,
                $this->supplier,
                $this->province,
            );
        }

        return $sheets;
    }
}
