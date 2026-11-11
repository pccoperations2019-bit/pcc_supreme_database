<?php

namespace App\Imports;

use App\Actions\MilkProduction\UpsertMilkProductionAction;
use App\Actions\MilkProduction\UpsertMilkSaleAction;
use App\Actions\Partner\UpsertPartnerAction;
use App\Enums\ProgramEnum;
use App\Imports\Concerns\WithDocumentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MilkProductionImport implements ShouldQueue, SkipsUnknownSheets, WithChunkReading, WithEvents, WithMultipleSheets
{
    use Queueable, WithDocumentStatus;

    protected ProgramEnum $program = ProgramEnum::DEFAULT;

    private const SKIP_SHEETS = ['sheet10'];

    private array $sheetNames = [];

    public function __construct(
        private readonly UpsertMilkProductionAction $upsertMilkProductionAction,
        private readonly UpsertMilkSaleAction $upsertMilkSaleAction,
        private readonly UpsertPartnerAction $upsertPartnerAction,
    ) {}

    public function chunkSize(): int
    {
        return 1000;
    }

    public function setProgram(ProgramEnum $program): void
    {
        $this->program = $program;
    }

    public function setSheetNames(array $sheetNames): void
    {
        $this->sheetNames = $sheetNames;
    }

    public function sheets(): array
    {
        $imports = [];
        $productionSheetNames = array_flip(array_map(
            fn (string $sheetName) => $this->normalizeSheetKey($sheetName),
            $this->get4DXProductionSheetNames()
        ));
        $salesSheetNames = array_flip(array_map(
            fn (string $sheetName) => $this->normalizeSheetKey($sheetName),
            $this->get4DXSalesSheetNames()
        ));

        $sheetNames = $this->sheetNames;

        if ($sheetNames !== []) {
            foreach ($sheetNames as $sheetName) {
                if (in_array($this->normalizeSheetKey($sheetName), self::SKIP_SHEETS, true)) {
                    continue;
                }

                $normalizedSheetName = $this->normalizeSheetKey($sheetName);

                if (isset($productionSheetNames[$normalizedSheetName])) {
                    $program = $this->getProgramFromSheetName($sheetName);
                    $import = new MilkProduction4DXSheetImport(
                        $this->upsertMilkProductionAction,
                        $this->upsertPartnerAction,
                    );
                    $import->setProgram($program);
                    $imports[$sheetName] = $import;

                    continue;
                }

                if (isset($salesSheetNames[$normalizedSheetName])) {
                    $program = $this->getProgramFromSheetName($sheetName);
                    $import = new MilkSales4DXSheetImport(
                        $this->upsertMilkSaleAction,
                        $this->upsertPartnerAction,
                    );
                    $import->setProgram($program);
                    $imports[$sheetName] = $import;

                    continue;
                }

                $program = $this->getProgramFromSheetName($sheetName);
                $import = new MilkProductionSheetImport(
                    $this->upsertMilkProductionAction,
                    $this->upsertMilkSaleAction,
                    $this->upsertPartnerAction,
                );
                $import->setProgram($program);
                $imports[$sheetName] = $import;
            }
        } else {

            foreach (range(0, 20) as $sheetIndex) {
                $import = new MilkProductionSheetImport(
                    $this->upsertMilkProductionAction,
                    $this->upsertMilkSaleAction,
                    $this->upsertPartnerAction,
                );
                $import->setProgram($this->program);
                $imports[$sheetIndex] = $import;
            }
        }

        return $imports;
    }

    private function getProgramFromSheetName(string $sheetName): ProgramEnum
    {
        $lowerSheetName = strtolower($sheetName);
        if (stripos($lowerSheetName, 'cbed') !== false) {
            return ProgramEnum::CBED;
        }

        return ProgramEnum::IH;
    }

    private function normalizeSheetKey(string $sheetName): string
    {
        return strtolower(trim($sheetName));
    }

    public function onUnknownSheet($sheetName): void {}

    private function get4DXProductionSheetNames(): array
    {
        return [
            'Data 2020 Milk Production',
            '2021 Milk Production',
            '2022 Milk Production',
            '2023 Milk Production',
            '2024 Milk Production',
            '2025 Milk Production',
        ];
    }

    private function get4DXSalesSheetNames(): array
    {
        return [
            'Data 2020 Milk SALES',
            '2021 Milk Sales',
            '2022 Milk Sales',
            '2023 Milk Sales',
            '2024 Milk Sales',
            '2025 Milk Sales',
        ];
    }
}
