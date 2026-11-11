<?php

namespace App\Exports;

use App\Models\MilkProduction;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MilkProductionMultipleSheetExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected int|array|null $year = null,
        protected int|array|null $centerId = null,
        protected int|array|null $partnerId = null,
        protected ?int $farmerId = null,
        protected int|array|null $month = null,
        protected ?int $week = null,
        protected ?string $program = null,
        protected ?string $startDate = null,
        protected ?string $endDate = null,
        protected ?string $status = null,
        protected ?string $milkingTime = null,
        protected ?string $sourceOfMilk = null,
        protected array $availableYears = [], // Add available years from filters
        protected string|array|null $region = null,
        protected string|array|null $province = null,
        protected string|array|null $city = null,
    ) {
        // Convert single year to array for consistency
        if (! is_array($this->year) && $this->year !== null) {
            $this->year = [$this->year];
        }
    }

    public function sheets(): array
    {
        $sheets = [];

        if ($this->year === null && $this->availableYears !== []) {
            $this->year = $this->availableYears;
        } elseif ($this->year === null) {
            $this->year = MilkProduction::distinct()
                ->orderBy('year')
                ->pluck('year')
                ->filter()
                ->toArray();
        }

        if (is_array($this->year) && count($this->year) > 0) {
            foreach ($this->year as $year) {
                $sheets[] = new MilkProductionExport(
                    $year,
                    $this->centerId,
                    $this->partnerId,
                    $this->farmerId,
                    $this->month,
                    $this->week,
                    $this->program,
                    $this->startDate,
                    $this->endDate,
                    $this->status,
                    $this->milkingTime,
                    $this->sourceOfMilk,
                    $this->region,
                    $this->province,
                    $this->city,
                );
            }
        } else {
            // Fallback for no data
            $sheets[] = new MilkProductionExport(
                null,
                $this->centerId,
                $this->partnerId,
                $this->farmerId,
                $this->month,
                $this->week,
                $this->program,
                $this->startDate,
                $this->endDate,
                $this->status,
                $this->milkingTime,
                $this->sourceOfMilk,
                $this->region,
                $this->province,
                $this->city,
            );
        }

        return $sheets;
    }
}
