<?php

namespace App\Exports\BuffaloDerivedProducts\Strategies;

use App\Exports\BuffaloDerivedProducts\BuffaloDerivedProductsFilters;
use App\Exports\BuffaloDerivedProducts\Contracts\BdpLayoutStrategy;
use App\Exports\BuffaloDerivedProducts\Support\BdpLocationResolver;
use App\Exports\BuffaloDerivedProducts\Support\BdpQueryBuilder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

final class DateAnnualLayoutStrategy implements BdpLayoutStrategy
{
    private array $headerMerges = [];

    private int $headerRows = 1;

    public function __construct(
        private BdpQueryBuilder $queryBuilder,
        private BdpLocationResolver $locationResolver,
    ) {}

    public function build(array $matrix, BuffaloDerivedProductsFilters $filters): array
    {
        $this->headerMerges = [];
        $this->headerRows = 1;

        return match ($filters->templateVariant ?? 'year-product') {
            'center-partner' => $this->buildDateAnnualByCenterPartner($matrix),
            'by-center-years' => $this->buildDateAnnualByCenterYears($filters),
            'center-partner-by-year' => $this->buildDateAnnualCenterPartnerByYear(),
            default => $this->buildDateAnnualSimple($matrix),
        };
    }

    private function buildDateAnnualSimple(array $matrix): array
    {
        $rows = [['YEAR', 'VALUE']];

        foreach ($matrix['years'] as $year) {
            $rows[] = [$year, $this->format($matrix['by_year'][$year] ?? 0.0)];
        }

        $rows[] = ['Grand Total', $this->format($matrix['grand_total'])];

        return $rows;
    }

    private function buildDateAnnualByCenterPartner(array $matrix): array
    {
        $types = $this->orderedTypes(array_keys($matrix['type_totals']));
        $rows = [['YEAR', 'Center', 'Partner', 'Grand Total']];

        foreach ($matrix['years'] as $year) {
            $rows[] = [
                $year,
                $this->format($matrix['by_year_type'][$year]['center'] ?? 0.0),
                $this->format($matrix['by_year_type'][$year]['partner'] ?? 0.0),
                $this->format($matrix['by_year'][$year] ?? 0.0),
            ];
        }

        $totalRow = ['Grand Total'];
        foreach ($types as $type) {
            $totalRow[] = $this->format($matrix['type_totals'][$type] ?? 0.0);
        }
        $totalRow[] = $this->format($matrix['grand_total']);
        $rows[] = $totalRow;

        return $rows;
    }

    private function buildDateAnnualByCenterYears(BuffaloDerivedProductsFilters $filters): array
    {
        $source = config("reports.bdp.sources.{$filters->product}.{$filters->type}");
        if (! $source || ! isset($source['table'], $source['metric'])) {
            return [['VALUE', 'No data available']];
        }

        $needsBreakdown = $this->needsPartnerBreakdown($filters);

        $rows = $needsBreakdown
            ? $this->queryBuilder->fetchCenterPartnerYearRows($source['table'], $source['metric'])
            : $this->queryBuilder->fetchCenterYearRows($source['table'], $source['metric']);

        if ($rows->isEmpty()) {
            return [['VALUE', 'No data available']];
        }

        $centerIds = $rows->pluck('center_id')->filter()->unique()->values()->all();
        $centerCodes = DB::table('centers')->whereIn('id', $centerIds)->pluck('code', 'id')->all();
        $centerLocations = $this->locationResolver->resolve($centerIds);

        $depth = $filters->locationDepth();
        $years = $rows->pluck('year')->unique()->sort()->values()->all();

        if (! $needsBreakdown) {
            return $this->buildByCenterYearsSimple($centerCodes, $centerLocations, $rows, $years, $depth);
        }

        return $this->buildByCenterYearsWithPartners($filters, $centerCodes, $centerLocations, $rows, $years, $depth);
    }

    private function buildByCenterYearsSimple(
        array $centerCodes,
        array $centerLocations,
        $rows,
        array $years,
        int $depth,
    ): array {
        $locationHeaders = $this->locationResolver->locationLabels($depth);
        $header = ['CENTER', ...$locationHeaders, ...array_map(fn (int $y) => (string) $y, $years), 'TOTAL'];

        $dataByCenter = [];
        foreach ($rows as $row) {
            $dataByCenter[$row->center_id][$row->year] = ($dataByCenter[$row->center_id][$row->year] ?? 0.0) + (float) $row->value;
        }

        $grandByYear = array_fill_keys($years, 0.0);
        $grandTotal = 0.0;
        $result = [$header];

        foreach ($dataByCenter as $centerId => $yearData) {
            $row = $this->locationRow($centerCodes[$centerId] ?? 'Unknown', $centerLocations[$centerId] ?? [], $depth);
            $total = 0.0;
            foreach ($years as $year) {
                $v = $yearData[$year] ?? 0.0;
                $total += $v;
                $grandByYear[$year] += $v;
                $row[] = $this->format($v);
            }
            $grandTotal += $total;
            $row[] = $this->format($total);
            $result[] = $row;
        }

        $result[] = $this->totalRow($locationHeaders, [], $years, $grandByYear, $grandTotal);

        return $result;
    }

    private function buildByCenterYearsWithPartners(
        BuffaloDerivedProductsFilters $filters,
        array $centerCodes,
        array $centerLocations,
        $rows,
        array $years,
        int $depth,
    ): array {
        $showFarmer = $filters->farmer !== null && $filters->farmer !== '' && $filters->farmer !== [];

        $allPartnerIds = $rows->pluck('partner_id')->filter()->unique()->values()->all();
        $allFarmerIds = $rows->pluck('farmer_id')->filter()->unique()->values()->all();
        $partnerLocations = $this->locationResolver->resolveByType('partner', $allPartnerIds);
        $farmerLocations = $this->locationResolver->resolveByType('farmer', $allFarmerIds);
        $partnerNames = $allPartnerIds !== [] ? DB::table('partners')->whereIn('id', $allPartnerIds)->pluck('name', 'id')->all() : [];
        $farmerNames = $this->resolveFarmerNames($allFarmerIds);

        $locationHeaders = $this->locationResolver->locationLabels($depth);
        $header = ['CENTER', ...$locationHeaders, 'PARTNER NAME'];
        if ($showFarmer) {
            $header[] = 'FARMER NAME';
        }
        $header = [...$header, ...array_map(fn (int $y) => (string) $y, $years), 'TOTAL'];

        $partnerValues = [];
        $farmerValues = [];
        $centerTotals = [];
        foreach ($rows as $row) {
            $c = $row->center_id;
            $p = $row->partner_id;
            $f = $row->farmer_id;
            $y = (int) $row->year;
            $v = (float) $row->value;
            if (! empty($f) && $showFarmer) {
                $farmerValues[$c][(int) $p][(int) $f][$y] = ($farmerValues[$c][(int) $p][(int) $f][$y] ?? 0.0) + $v;
            } elseif (! empty($p)) {
                $partnerValues[$c][(int) $p][$y] = ($partnerValues[$c][(int) $p][$y] ?? 0.0) + $v;
            } else {
                $centerTotals[$c][$y] = ($centerTotals[$c][$y] ?? 0.0) + $v;
            }
        }

        $grandByYear = array_fill_keys($years, 0.0);
        $grandTotal = 0.0;
        $result = [$header];

        foreach ($centerCodes as $centerId => $code) {
            $hasP = isset($partnerValues[$centerId]) && $partnerValues[$centerId] !== [];
            $hasF = isset($farmerValues[$centerId]) && $farmerValues[$centerId] !== [];
            $hasC = isset($centerTotals[$centerId]) && $centerTotals[$centerId] !== [];
            if (! $hasP && ! $hasF && ! $hasC) {
                continue;
            }

            $centerCode = strtoupper((string) $code);

            if ($hasC || (! $hasP && ! $hasF)) {
                $row = [...$this->locationRow($centerCode, $centerLocations[$centerId] ?? [], $depth), "PCC at {$centerCode}"];
                if ($showFarmer) {
                    $row[] = '';
                }
                $total = $this->appendYearValues($row, $years, $centerTotals[$centerId] ?? [], $grandByYear);
                $grandTotal += $total;
                if ($hasC) {
                    $result[] = $row;
                }
            }

            foreach ($partnerValues[$centerId] ?? [] as $pId => $yearVals) {
                $loc = $this->locationCellsForPartner($partnerLocations[$pId] ?? null, $centerCode, $depth);
                $row = [...$loc, (string) ($partnerNames[$pId] ?? 'Unknown Partner')];
                if ($showFarmer) {
                    $row[] = '';
                }
                $total = $this->appendYearValues($row, $years, $yearVals, $grandByYear);
                $grandTotal += $total;
                $result[] = $row;
            }

            foreach ($farmerValues[$centerId] ?? [] as $pId => $farmers) {
                foreach ($farmers as $fId => $yearVals) {
                    $loc = $this->locationCellsForFarmer($farmerLocations[$fId] ?? null, $centerCode, $depth);
                    $row = [...$loc, (string) ($partnerNames[$pId] ?? ''), (string) ($farmerNames[$fId] ?? '')];
                    $total = $this->appendYearValues($row, $years, $yearVals, $grandByYear);
                    $grandTotal += $total;
                    $result[] = $row;
                }
            }
        }

        $result[] = $this->totalRow($locationHeaders, $header, $years, $grandByYear, $grandTotal, $showFarmer);

        return $result;
    }

    private function locationRow(string $code, array $address, int $depth): array
    {
        $loc = $this->locationResolver->resolveLocationLabels($address, $depth);
        $row = [strtoupper($code)];
        if ($depth >= 2) {
            $row[] = $loc['region'];
        }
        if ($depth >= 3) {
            $row[] = $loc['province'];
        }
        if ($depth >= 4) {
            $row[] = $loc['municipality'];
        }
        if ($depth >= 5) {
            $row[] = $loc['district'];
        }

        return $row;
    }

    private function locationCellsForPartner(?array $address, string $centerCode, int $depth): array
    {
        if ($address === null) {
            return [strtoupper($centerCode), ...array_fill(0, max(0, $depth - 1), '')];
        }
        $loc = $this->locationResolver->resolveLocationLabels($address, $depth);
        $row = [strtoupper($centerCode)];
        if ($depth >= 2) {
            $row[] = $loc['region'];
        }
        if ($depth >= 3) {
            $row[] = $loc['province'];
        }
        if ($depth >= 4) {
            $row[] = $loc['municipality'];
        }
        if ($depth >= 5) {
            $row[] = $loc['district'];
        }

        return $row;
    }

    private function locationCellsForFarmer(?array $address, string $centerCode, int $depth): array
    {
        return $this->locationCellsForPartner($address, $centerCode, $depth);
    }

    private function appendYearValues(array &$row, array $years, array $yearVals, array &$grandByYear): float
    {
        $total = 0.0;
        foreach ($years as $year) {
            $v = $yearVals[$year] ?? 0.0;
            $total += $v;
            $grandByYear[$year] += $v;
            $row[] = $this->format($v);
        }
        $row[] = $this->format($total);

        return $total;
    }

    private function totalRow(array $locationHeaders, array $header, array $years, array $grandByYear, float $grandTotal, bool $hasFarmer = false): array
    {
        // Used by simple path — header already includes location count
        $extra = str_contains(implode(',', $header), 'PARTNER NAME') ? 1 + ($hasFarmer ? 1 : 0) : 0;
        $row = array_merge(['Total'], array_fill(0, count($locationHeaders) + $extra, ''));
        foreach ($years as $year) {
            $row[] = $this->format($grandByYear[$year]);
        }
        $row[] = $this->format($grandTotal);

        return $row;
    }

    private function resolveFarmerNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = DB::table('farmers')->whereIn('id', $ids)->select('id', 'code', 'first_name', 'middle_name', 'last_name', 'extension_name')->get();
        $map = [];
        foreach ($rows as $f) {
            $name = trim(sprintf('%s %s %s %s', $f->first_name ?? '', $f->middle_name ?? '', $f->last_name ?? '', $f->extension_name ?? ''));
            $name = preg_replace('/\s+/', ' ', $name);
            $map[$f->id] = $name !== '' ? $name : (string) ($f->code ?? '');
        }

        return $map;
    }

    private function needsPartnerBreakdown(BuffaloDerivedProductsFilters $filters): bool
    {
        return $this->partnerBreakdownEnabled($filters);
    }

    private function buildDateAnnualCenterPartnerByYear(): array
    {
        $source = config("reports.bdp.sources.{$this->queryBuilder->filters->product}.{$this->queryBuilder->filters->type}");
        if (! $source || ! isset($source['table'], $source['metric'])) {
            return [['VALUE', 'No data available']];
        }
        $rows = $this->queryBuilder->fetchCenterPartnerYearRows($source['table'], $source['metric']);
        if ($rows->isEmpty()) {
            return [['VALUE', 'No data available']];
        }
        $centerIds = $rows->pluck('center_id')->filter()->unique()->values()->all();
        $centerCodes = DB::table('centers')->whereIn('id', $centerIds)->pluck('code', 'id')->all();
        $centerLocations = $this->locationResolver->resolve($centerIds);
        $filters = $this->queryBuilder->filters;
        $partnerLocations = [];
        $farmerLocations = [];
        if ($this->needsPartnerBreakdown($filters) || $filters->farmer) {
            $allPartnerIds = $rows->pluck('partner_id')->filter()->unique()->values()->all();
            $allFarmerIds = $rows->pluck('farmer_id')->filter()->unique()->values()->all();
            $partnerLocations = $this->locationResolver->resolveByType('partner', $allPartnerIds);
            $farmerLocations = $this->locationResolver->resolveByType('farmer', $allFarmerIds);
        }
        $years = $rows->pluck('year')->unique()->sort()->values()->all();
        $showPartnerName = $this->partnerBreakdownEnabled($filters);
        $showFarmerName = $filters->farmer !== null && $filters->farmer !== '' && $filters->farmer !== [];
        $showFarmerBreakdown = $showFarmerName;
        $partnerNames = [];
        if ($showPartnerName || $showFarmerName) {
            $allPartnerIds = $rows->pluck('partner_id')->filter()->unique()->values()->all();
            $partnerNames = $allPartnerIds !== []
                ? DB::table('partners')->whereIn('id', $allPartnerIds)->pluck('name', 'id')->all()
                : [];
        }
        $farmerNames = [];
        if ($showFarmerBreakdown) {
            $allFarmerIds = $rows->pluck('farmer_id')->filter()->unique()->values()->all();
            if ($allFarmerIds !== []) {
                $farmerNames = $this->resolveFarmerNames($allFarmerIds);
            }
        }
        $dataByCenter = [];
        $partnerValues = [];
        $farmerValues = [];
        foreach ($rows as $row) {
            $centerId = $row->center_id;
            $partnerId = $row->partner_id;
            $farmerId = $row->farmer_id;
            $year = (int) $row->year;
            $value = (float) $row->value;
            $isFarmerRow = ! empty($farmerId) && $showFarmerBreakdown;
            $type = (empty($partnerId) && ! $isFarmerRow) ? 'center' : 'partner';
            if (! isset($dataByCenter[$centerId])) {
                $dataByCenter[$centerId] = [];
            }
            if (! isset($dataByCenter[$centerId][$year])) {
                $dataByCenter[$centerId][$year] = ['center' => 0.0, 'partner' => 0.0];
            }
            $dataByCenter[$centerId][$year][$type] = ($dataByCenter[$centerId][$year][$type] ?? 0.0) + $value;
            if ($isFarmerRow) {
                $farmerValues[$centerId][(int) $partnerId][(int) $farmerId][$year] = ($farmerValues[$centerId][(int) $partnerId][(int) $farmerId][$year] ?? 0.0) + $value;

                continue;
            }
            if (! empty($partnerId)) {
                $partnerValues[$centerId][(int) $partnerId][$year] = ($partnerValues[$centerId][(int) $partnerId][$year] ?? 0.0) + $value;
            }
        }
        $this->headerRows = 2;
        $depth = $filters->locationDepth();
        $locationHeaders = $this->locationResolver->locationLabels($depth);
        $locationLabelCount = count($locationHeaders);
        $headerRow1 = [''];
        $headerRow2 = ['CENTER'];
        foreach ($locationHeaders as $h) {
            $headerRow1[] = '';
            $headerRow2[] = $h;
        }
        if ($showPartnerName || $showFarmerName) {
            $headerRow1[] = '';
            $headerRow2[] = 'PARTNER NAME';
        }
        if ($showFarmerName) {
            $headerRow1[] = '';
            $headerRow2[] = 'FARMER NAME';
        }
        $labelColumnCount = 1 + $locationLabelCount + (($showPartnerName || $showFarmerName) ? 1 : 0) + ($showFarmerName ? 1 : 0);
        $columnIndex = $labelColumnCount + 1;
        foreach ($years as $year) {
            $headerRow1[] = (string) $year;
            $headerRow1[] = '';
            $headerRow1[] = '';
            $headerRow2[] = 'Center';
            $headerRow2[] = 'Partner';
            $headerRow2[] = 'Total';
            $this->headerMerges[] = sprintf('%s1:%s1', Coordinate::stringFromColumnIndex($columnIndex), Coordinate::stringFromColumnIndex($columnIndex + 2));
            $columnIndex += 3;
        }
        $headerRow1[] = 'GRAND TOTAL';
        $headerRow2[] = '';
        $result = [$headerRow1, $headerRow2];
        $grandTotalsByYear = [];
        foreach ($years as $year) {
            $grandTotalsByYear[$year] = ['center' => 0.0, 'partner' => 0.0, 'total' => 0.0];
        }
        $grandTotalOverall = 0.0;
        if ($showPartnerName || $showFarmerName) {
            foreach ($dataByCenter as $centerId => $yearData) {
                if ($filters->partner && $filters->partner !== 'all' && ($partnerValues[$centerId] ?? []) === [] && ($farmerValues[$centerId] ?? []) === []) {
                    continue;
                }
                if ($filters->farmer && $filters->farmer !== 'all' && ($farmerValues[$centerId] ?? []) === []) {
                    continue;
                }
                $centerCode = strtoupper((string) ($centerCodes[$centerId] ?? 'Unknown'));
                $locCells = $this->buildLocCells($centerLocations[$centerId] ?? [], $depth, $locationLabelCount);
                if (! $filters->farmer || $filters->farmer === 'all') {
                    $ihRow = array_merge([$centerCode], $locCells, ["PCC at {$centerCode}"]);
                    if ($showFarmerName) {
                        $ihRow[] = '';
                    }
                    $ihGrand = 0.0;
                    $hasIh = false;
                    foreach ($years as $year) {
                        $ihVal = $yearData[$year]['center'] ?? 0.0;
                        if ($ihVal !== 0.0) {
                            $hasIh = true;
                        }
                        $ihGrand += $ihVal;
                        $grandTotalsByYear[$year]['center'] += $ihVal;
                        $grandTotalsByYear[$year]['total'] += $ihVal;
                        $ihRow[] = $this->format($ihVal);
                        $ihRow[] = '';
                        $ihRow[] = $this->format($ihVal);
                    }
                    if ($hasIh) {
                        $grandTotalOverall += $ihGrand;
                        $ihRow[] = $this->format($ihGrand);
                        $result[] = $ihRow;
                    }
                }
                $centerPartners = $partnerValues[$centerId] ?? [];
                uksort($centerPartners, fn ($a, $b) => strcmp((string) ($partnerNames[$a] ?? ''), (string) ($partnerNames[$b] ?? '')));
                foreach ($centerPartners as $partnerId => $yearVals) {
                    $pLocCells = $this->buildLocCells($partnerLocations[$partnerId] ?? null, $depth, $locationLabelCount, true);
                    $pRow = array_merge([$centerCode], $pLocCells, [(string) ($partnerNames[$partnerId] ?? 'Unknown Partner')]);
                    if ($showFarmerName) {
                        $pRow[] = '';
                    }
                    $pGrand = 0.0;
                    foreach ($years as $year) {
                        $pVal = $yearVals[$year] ?? 0.0;
                        $pGrand += $pVal;
                        $grandTotalsByYear[$year]['partner'] += $pVal;
                        $grandTotalsByYear[$year]['total'] += $pVal;
                        $pRow[] = '';
                        $pRow[] = $this->format($pVal);
                        $pRow[] = $this->format($pVal);
                    }
                    $grandTotalOverall += $pGrand;
                    $pRow[] = $this->format($pGrand);
                    $result[] = $pRow;
                }
                $centerFarmers = $farmerValues[$centerId] ?? [];
                foreach ($centerFarmers as $partnerId => $farmers) {
                    uksort($farmers, fn ($a, $b) => strcmp((string) ($farmerNames[$a] ?? ''), (string) ($farmerNames[$b] ?? '')));
                    foreach ($farmers as $farmerId => $yearVals) {
                        $fLocCells = $this->buildLocCells($farmerLocations[$farmerId] ?? null, $depth, $locationLabelCount, true);
                        $fRow = array_merge([$centerCode], $fLocCells, [(string) ($partnerNames[$partnerId] ?? ''), (string) ($farmerNames[$farmerId] ?? '')]);
                        $fGrand = 0.0;
                        foreach ($years as $year) {
                            $fVal = $yearVals[$year] ?? 0.0;
                            $fGrand += $fVal;
                            $grandTotalsByYear[$year]['partner'] += $fVal;
                            $grandTotalsByYear[$year]['total'] += $fVal;
                            $fRow[] = '';
                            $fRow[] = $this->format($fVal);
                            $fRow[] = $this->format($fVal);
                        }
                        $grandTotalOverall += $fGrand;
                        $fRow[] = $this->format($fGrand);
                        $result[] = $fRow;
                    }
                }
            }
        } else {
            foreach ($dataByCenter as $centerId => $yearData) {
                $centerCode = strtoupper((string) ($centerCodes[$centerId] ?? 'Unknown'));
                $row = [$centerCode, ...$this->buildLocCells($centerLocations[$centerId] ?? [], $depth, $locationLabelCount)];
                $centerGrandTotal = 0.0;
                foreach ($years as $year) {
                    $centerVal = $yearData[$year]['center'] ?? 0.0;
                    $partnerVal = $yearData[$year]['partner'] ?? 0.0;
                    $yearTotal = $centerVal + $partnerVal;
                    $centerGrandTotal += $yearTotal;
                    $grandTotalsByYear[$year]['center'] += $centerVal;
                    $grandTotalsByYear[$year]['partner'] += $partnerVal;
                    $grandTotalsByYear[$year]['total'] += $yearTotal;
                    $row[] = $this->format($centerVal);
                    $row[] = $this->format($partnerVal);
                    $row[] = $this->format($yearTotal);
                }
                $grandTotalOverall += $centerGrandTotal;
                $row[] = $this->format($centerGrandTotal);
                $result[] = $row;
            }
        }
        $totalRow = array_merge(['Total'], array_fill(0, $labelColumnCount - 1, ''));
        foreach ($years as $year) {
            $totalRow[] = $this->format($grandTotalsByYear[$year]['center']);
            $totalRow[] = $this->format($grandTotalsByYear[$year]['partner']);
            $totalRow[] = $this->format($grandTotalsByYear[$year]['total']);
        }
        $totalRow[] = $this->format($grandTotalOverall);
        $result[] = $totalRow;

        return $result;
    }

    private function buildLocCells(?array $address, int $depth, int $labelCount, bool $emptyIfMissing = false): array
    {
        if ($address === null && $emptyIfMissing) {
            return array_fill(0, $labelCount, '');
        }
        $loc = $this->locationResolver->resolveLocationLabels($address ?? [], $depth);
        $cells = [];
        if ($labelCount >= 1 && $depth >= 2) {
            $cells[] = $loc['region'];
        }
        if ($labelCount >= 2 && $depth >= 3) {
            $cells[] = $loc['province'];
        }
        if ($labelCount >= 3 && $depth >= 4) {
            $cells[] = $loc['municipality'];
        }
        if ($labelCount >= 4 && $depth >= 5) {
            $cells[] = $loc['district'];
        }
        // Pad to expected count (handles depth gaps)
        while (count($cells) < $labelCount) {
            $cells[] = '';
        }

        return array_slice($cells, 0, $labelCount);
    }

    private function partnerBreakdownEnabled(BuffaloDerivedProductsFilters $filters): bool
    {
        $partner = $filters->partner;

        return $partner !== null && $partner !== '' && $partner !== [];
    }

    private function orderedTypes(array $types): array
    {
        $preferred = ['center', 'partner'];
        $ordered = [];
        foreach ($preferred as $type) {
            if (in_array($type, $types, true)) {
                $ordered[] = $type;
            }
        }
        foreach ($types as $type) {
            if (! in_array($type, $ordered, true)) {
                $ordered[] = $type;
            }
        }

        return $ordered;
    }

    private function format(float $value): string
    {
        return number_format($value, 2);
    }

    public function headerMerges(): array
    {
        return $this->headerMerges;
    }

    public function headerRows(): int
    {
        return $this->headerRows;
    }
}
