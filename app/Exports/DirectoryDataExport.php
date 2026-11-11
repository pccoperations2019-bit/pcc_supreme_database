<?php

namespace App\Exports;

use App\Enums\AddressDistrictEnum;
use App\Enums\AddressRegionEnum;
use App\Enums\DocumentEnum;
use App\Enums\ProfileStatusEnum;
use App\Exports\Directory\Contracts\DirectoryEntityStrategy;
use App\Exports\Directory\DirectoryFilters;
use App\Exports\Directory\Strategies\CentersDirectoryStrategy;
use App\Exports\Directory\Strategies\FarmersDirectoryStrategy;
use App\Exports\Directory\Strategies\PartnersDirectoryStrategy;
use App\Exports\Directory\Support\DirectoryAccreditationResolver;
use App\Exports\Directory\Support\DirectoryBdpProductResolver;
use App\Exports\Directory\Support\DirectoryFiguresAggregator;
use App\Exports\Directory\Support\DirectoryLocationResolver;
use App\Exports\Directory\Support\DirectoryMetadata;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Directory Data report export.
 *
 * Supports three entity types: Centers, Partners, and Farmers.
 * Each entity type supports List (detailed records) and Figures (aggregate counts) modes.
 * Implements hierarchical drilldown: Center → Region → Province → Municipality → District.
 *
 * Uses FromArray instead of FromQuery to support:
 * - Dynamic list/figures matrix generation
 * - Custom header rows and empty-data responses
 * - Cross-entity batched resolvers for row enrichment
 * - Figures aggregation that requires post-query processing
 *
 * A future FromQuery migration would require separate designs for list vs figures.
 */
class DirectoryDataExport implements FromArray, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const HEADER_ROW_COUNT = 1;

    private DirectoryFilters $filters;

    private DirectoryFiguresAggregator $figuresAggregator;

    public function __construct(
        protected string $template = 'centers',
        protected ?string $templateVariant = null,
        protected ?string $listAndFigures = null,
        protected int|string|array|null $centerId = null,
        protected int|string|array|null $partnerId = null,
        protected int|string|array|null $farmerId = null,
        protected string|array|null $region = null,
        protected string|array|null $province = null,
        protected string|array|null $district = null,
        protected string|array|null $city = null,
        protected string|array|null $partnerCategory = null,
        protected string|array|null $status = null,
        protected string|array|null $project = null,
        protected string|array|null $accreditation = null,
        protected string|array|null $division = null,
        protected string|array|null $businessType = null,
    ) {
        $resolvedPartnerCategory = $partnerCategory ?? $businessType;
        $this->filters = DirectoryFilters::fromArguments(
            template: $template,
            templateVariant: $templateVariant,
            listAndFigures: $listAndFigures,
            centerId: $centerId,
            partnerId: $partnerId,
            farmerId: $farmerId,
            region: $region,
            province: $province,
            district: $district,
            city: $city,
            partnerCategory: $resolvedPartnerCategory,
            status: $status,
            project: $project,
            accreditation: $accreditation,
            division: $division,
        );
        // Back-compat: allow passing variant via template value like "centers:all-data"
        $this->templateVariant = $this->filters->templateVariant;
        $this->figuresAggregator = new DirectoryFiguresAggregator;
    }

    public function title(): string
    {
        // Variant-aware title; for now all variants resolve to "All Data (...)".
        // Future variants (e.g. assistance-status) can branch here without changing callers.
        $variantLabel = match ($this->templateVariant) {
            'all-data', null => 'All Data',
            default => ucwords(str_replace('-', ' ', $this->templateVariant)),
        };

        return match ($this->template) {
            'centers' => "{$variantLabel} (Centers)",
            'partners' => "{$variantLabel} (Partners)",
            'farmers' => "{$variantLabel} (Farmers)",
            default => 'Directory Data Report',
        };
    }

    public function array(): array
    {
        return match ($this->listAndFigures) {
            'list' => $this->generateListOutput(),
            'figures' => $this->generateFiguresOutput(),
            default => $this->generateListOutput(),
        };
    }

    private function generateListOutput(): array
    {
        $strategy = $this->getStrategy();
        $data = $strategy->query($this->filters)->get();

        if ($data->isEmpty()) {
            return [['No data available']];
        }

        $depth = $this->filters->locationDepth();
        $variant = $this->filters->templateVariant;
        $headers = $strategy->listHeaders($depth, $variant);
        $rows = $strategy->mapListRows($data, $depth, $variant);

        return [...$headers, ...$rows];
    }

    private function generateFiguresOutput(): array
    {
        $strategy = $this->getStrategy();
        $data = $strategy->query($this->filters)->get();

        if ($data->isEmpty()) {
            return [['No data available']];
        }

        $depth = $this->filters->locationDepth();
        $variant = $this->filters->templateVariant;

        // Status variant uses pivoted Active/Inactive/TOTAL counts instead of generic aggregator.
        if ($variant === 'status' && in_array($this->template, ['centers', 'partners', 'farmers'], true)) {
            return $this->generateStatusFiguresOutput($data, $depth);
        }

        // Program variant uses pivoted program counts per CENTER.
        if ($variant === 'program') {
            return $this->generateProgramFiguresOutput($data, $depth);
        }

        // Accreditations variant uses per-accreditation counts (no Date Accredited, 1 per accredited entity).
        if ($variant === 'accreditations') {
            return $this->generateAccreditationsFiguresOutput($data, $depth);
        }

        if ($variant === 'date-assisted') {
            return $this->generateDateAssistedFiguresOutput($data, $depth);
        }

        if (in_array($variant, ['products', 'buffalo-derived-products'], true)) {
            return $this->generateProductsFiguresOutput($data, $depth);
        }

        // Partners/Farmers list one row per record so PARTNER/FARMER NAME is always identifiable,
        // mirroring the list export. Centers aggregate per area as before.
        if ($strategy->entityType() !== 'center') {
            return $this->generateEntityAllDataFiguresOutput($data, $depth);
        }

        $headers = $strategy->figuresHeaders($depth, $variant);
        $figures = $this->figuresAggregator->aggregate(
            data: $data,
            depth: $depth,
            dimensions: fn ($entity, int $figureDepth): array => $strategy->figureDimensions($entity, $figureDepth, $variant),
            totalLabel: $strategy->figuresCountLabel(),
        );

        return [...$headers, ...$figures];
    }

    private function generateEntityAllDataFiguresOutput(Collection $data, int $depth): array
    {
        $strategy = $this->getStrategy();
        $entityType = $strategy->entityType();
        $nameHeader = $this->entityNameHeader($entityType);
        $headers = $strategy->figuresHeaders($depth, $this->filters->templateVariant);

        $ids = $data->pluck('id')->unique()->values()->all();
        $addresses = DirectoryLocationResolver::resolveAddresses($entityType, $ids);

        $rows = [];
        foreach ($data as $entity) {
            $address = $addresses[$entity->id] ?? null;
            $rows[] = [
                ...$this->entityLocationValues($entity, $address, $entityType, $depth),
                $this->entityName($entity, $entityType),
                1,
            ];
        }

        return [...$headers, ...$rows, $this->figuresTotalRow($depth, $nameHeader, [$data->count()])];
    }

    private function generateStatusFiguresOutput(Collection $data, int $depth): array
    {
        $strategy = $this->getStrategy();
        $headers = $strategy->figuresHeaders($depth, 'status');

        if (in_array($this->template, ['partners', 'farmers'], true)) {
            $entityType = $strategy->entityType();
            $nameHeader = $this->entityNameHeader($entityType);
            $headers = $strategy->figuresHeaders($depth, 'status');

            $entityIds = $data->pluck('id')->unique()->values()->all();
            $addresses = DirectoryLocationResolver::resolveAddresses($entityType, $entityIds);

            $rows = [];
            $sumActive = 0;
            $sumInactive = 0;
            foreach ($data as $entity) {
                $address = $addresses[$entity->id] ?? null;
                $row = [
                    ...$this->entityLocationValues($entity, $address, $entityType, $depth),
                    $this->entityName($entity, $entityType),
                ];

                $statusLabel = $this->labelStatus($entity->status);
                $isActive = $statusLabel === 'Active';
                $isInactive = $statusLabel === 'Inactive';

                $row[] = $isActive ? 1 : '';
                $row[] = $isInactive ? 1 : '';
                $row[] = 1;
                $rows[] = $row;

                $sumActive += $isActive ? 1 : 0;
                $sumInactive += $isInactive ? 1 : 0;
            }

            return [
                ...$headers,
                ...$rows,
                $this->figuresTotalRow($depth, $nameHeader, [
                    $sumActive === 0 ? '' : $sumActive,
                    $sumInactive === 0 ? '' : $sumInactive,
                    $data->count(),
                ]),
            ];
        }

        // Centers: one row per center (per-entity pivot)
        $centerIds = $data->pluck('id')->unique()->values()->all();
        $addresses = DirectoryLocationResolver::resolveAddresses('center', $centerIds);

        $rows = [];
        $sumActive = 0;
        $sumInactive = 0;

        foreach ($data as $center) {
            $address = $addresses[$center->id] ?? null;
            $row = [$center->code];

            if ($depth >= 2) {
                $row[] = $this->labelRegion($address->region ?? null);
            }
            if ($depth >= 3) {
                $row[] = $address->province ?? '';
            }
            if ($depth >= 4) {
                $row[] = $address->city ?? '';
            }
            if ($depth >= 5) {
                $row[] = $this->labelDistrict($address->district ?? null);
            }

            $statusLabel = $this->labelStatus($center->status);
            $isActive = $statusLabel === 'Active';
            $isInactive = $statusLabel === 'Inactive';

            $activeCell = $isActive ? 1 : '';
            $inactiveCell = $isInactive ? 1 : '';

            if ($isActive) {
                $sumActive++;
            }
            if ($isInactive) {
                $sumInactive++;
            }

            $row[] = $activeCell;
            $row[] = $inactiveCell;
            $row[] = 1;

            $rows[] = $row;
        }

        // Grand total row: TOTAL in first column, blanks for remaining location cols, then sums
        $totalRow = ['TOTAL'];
        for ($i = 2; $i <= $depth; $i++) {
            $totalRow[] = '';
        }
        $totalRow[] = $sumActive === 0 ? '' : $sumActive;
        $totalRow[] = $sumInactive === 0 ? '' : $sumInactive;
        $totalRow[] = $data->count();

        return [...$headers, ...$rows, $totalRow];
    }

    private function generateProgramFiguresOutput(Collection $data, int $depth): array
    {
        $strategy = $this->getStrategy();
        $entityType = $strategy->entityType();
        $strategy->figuresCountLabel();

        $ids = $data->pluck('id')->unique()->values()->all();
        $addresses = DirectoryLocationResolver::resolveAddresses($entityType, $ids);

        $programLabels = ['CBED', 'CBIN', 'CCDP', 'CHB', 'PL480', 'EPAHP'];

        $groups = [];
        foreach ($data as $entity) {
            $address = $addresses[$entity->id] ?? null;
            $keyValues = $this->entityLocationValues($entity, $address, $entityType, $depth);
            if ($entityType !== 'center') {
                $keyValues[] = $this->entityName($entity, $entityType);
            }
            $key = serialize($keyValues);

            if (! isset($groups[$key])) {
                $groups[$key] = ['values' => $keyValues, 'programCounts' => array_fill_keys($programLabels, 0)];
            }

            $programs = $this->getEntityPrograms($entity, $entityType);
            foreach ($programs as $code) {
                $label = $this->programLabel($code);
                if ($label !== '') {
                    $groups[$key]['programCounts'][$label]++;
                }
            }
        }

        usort($groups, fn (array $a, array $b): int => strcmp((string) $a['values'][0], (string) $b['values'][0]));

        $rows = [];
        $sums = array_fill_keys($programLabels, 0);
        foreach ($groups as $group) {
            $row = $group['values'];
            $total = 0;
            foreach ($programLabels as $label) {
                $count = $group['programCounts'][$label];
                $row[] = $count > 0 ? $count : '';
                $sums[$label] += $count;
                $total += $count;
            }
            $row[] = $total;
            $rows[] = $row;
        }

        $locationHeaders = $this->figuresLocationHeaders($depth);
        if ($entityType === 'partner') {
            $locationHeaders[] = 'PARTNER NAME';
        } elseif ($entityType === 'farmer') {
            $locationHeaders[] = 'FARMER NAME';
        }
        $headers = array_merge($locationHeaders, $programLabels, ['TOTAL']);

        $totalValues = [];
        foreach ($programLabels as $label) {
            $totalValues[] = $sums[$label] > 0 ? $sums[$label] : '';
        }
        $totalValues[] = array_sum($sums);

        return [
            $headers,
            ...$rows,
            $this->figuresTotalRow($depth, $entityType === 'center' ? null : 'name', $totalValues),
        ];
    }

    private function generateAccreditationsFiguresOutput(Collection $data, int $depth): array
    {
        $strategy = $this->getStrategy();
        $entityType = $strategy->entityType();
        $headers = $strategy->figuresHeaders($depth, 'accreditations');

        $figureColumns = ['Accredited', 'On Process', 'For Application', 'For Renewal', 'No Application'];

        // Map normalized accreditation filter to DocumentEnum values (supports all enums)
        $filterValue = $this->filters->accreditation;
        $filterDocTypes = null;
        if ($filterValue !== null) {
            $values = is_array($filterValue) ? $filterValue : [$filterValue];
            $filterDocTypes = [];
            foreach ($values as $v) {
                $norm = strtolower(trim((string) $v));
                $enumNorm = str_replace('_', '-', $norm);
                if (DocumentEnum::tryFrom($enumNorm) !== null) {
                    $filterDocTypes[] = $enumNorm;

                    continue;
                }
                // Handle mPhilGEPS alias
                if (in_array($norm, ['mphilgeps', 'm_philgeps', 'phil_geps'], true)) {
                    $filterDocTypes[] = DocumentEnum::PHILGEPS->value;

                    continue;
                }
                // Try label match
                foreach (DocumentEnum::labels() as $enumVal => $label) {
                    if (strtolower($label) === $norm || strtolower($enumVal) === $norm) {
                        $filterDocTypes[] = $enumVal;
                        break;
                    }
                }
            }
            $filterDocTypes = $filterDocTypes === [] ? null : array_values(array_unique($filterDocTypes));
        }

        $allDocTypes = [
            DocumentEnum::CSO->value,
            DocumentEnum::FDA_LTO->value,
            DocumentEnum::NDA_LTO->value,
            DocumentEnum::PHILGEPS->value,
            DocumentEnum::FFEDIS->value,
            DocumentEnum::RSBSA->value,
        ];
        // If filter is a DocumentEnum not in the 6 (e.g., bir, sec), still respect it as relevant
        $relevantDocTypes = $filterDocTypes ?? $allDocTypes;

        // Resolve addresses for location grouping
        $ids = $data->pluck('id')->unique()->values()->all();
        $addresses = DirectoryLocationResolver::resolveAddresses($entityType, $ids);

        // Fetch accreditation documents per relevant type
        $docsByDocType = [];
        if ($entityType === 'center') {
            $codes = $data->pluck('code')->filter()->values()->all();
            foreach ($relevantDocTypes as $docType) {
                $docsByDocType[$docType] = DirectoryAccreditationResolver::getDocuments('center', $codes, $docType);
            }
        } else {
            $entityIds = $data->pluck('id')->filter()->values()->all();
            foreach ($relevantDocTypes as $docType) {
                $docsByDocType[$docType] = DirectoryAccreditationResolver::getDocuments($entityType, $entityIds, $docType);
            }
        }

        $mapStatusToColumn = function (?string $status): string {
            $norm = strtolower(trim((string) $status));
            if ($norm === '') {
                return 'No Application';
            }
            if (in_array($norm, ['accredited', 'platinum', 'red_accreditation', 'red_registration', 'retailer_of_bodaco', 'compliant'], true)) {
                return 'Accredited';
            }
            if (in_array($norm, ['for_processing', 'processing', 'on_process', 'on process'], true)) {
                return 'On Process';
            }
            if (in_array($norm, ['for_renewal', 'renewal'], true)) {
                return 'For Renewal';
            }
            if (in_array($norm, ['for_endorsement', 'endorsement', 'for_application', 'application'], true)) {
                return 'For Application';
            }
            // Fallback: use resolver with no fallback to determine if status is accredited-like
            $resolved = DirectoryAccreditationResolver::resolveStatus($status, false);
            if (trim($resolved) !== '') {
                return str_contains(strtolower($resolved), 'accredited') ? 'Accredited' : 'For Application';
            }

            return 'No Application';
        };

        $getEntityFigureColumn = function (object $entity) use ($relevantDocTypes, $docsByDocType, $entityType, $mapStatusToColumn): string {
            $priority = ['No Application' => 1, 'For Application' => 2, 'On Process' => 3, 'For Renewal' => 4, 'Accredited' => 5];
            $bestColumn = 'No Application';
            $bestScore = 1;
            $foundAny = false;
            foreach ($relevantDocTypes as $docType) {
                $key = $entityType === 'center' ? ($entity->code ?? '') : (string) ($entity->id ?? '');
                $doc = $docsByDocType[$docType][$key] ?? null;
                $status = $doc?->compliance_status ?? null;
                // If enum, get value
                if (is_object($status) && method_exists($status, 'value')) {
                    $status = $status->value;
                } elseif (is_object($status)) {
                    $status = (string) $status;
                }
                $col = $mapStatusToColumn($status);
                // If entity has no doc and no has, it will be No Application; we still consider it
                $foundAny = true;
                $score = $priority[$col] ?? 1;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestColumn = $col;
                    if ($bestColumn === 'Accredited') {
                        break; // highest priority
                    }
                }
            }

            return $foundAny ? $bestColumn : 'No Application';
        };

        // Centers: one row per center (per-entity), partners/farmers: grouped by location
        if ($entityType === 'center') {
            $rows = [];
            $sums = array_fill_keys($figureColumns, 0);
            foreach ($data as $entity) {
                $address = $addresses[$entity->id] ?? null;
                $row = [$entity->code ?? ''];
                if ($depth >= 2) {
                    $row[] = $this->labelRegion($address->region ?? null);
                }
                if ($depth >= 3) {
                    $row[] = $address->province ?? '';
                }
                if ($depth >= 4) {
                    $row[] = $address->city ?? '';
                }
                if ($depth >= 5) {
                    $row[] = $this->labelDistrict($address->district ?? null);
                }
                $col = $getEntityFigureColumn($entity);
                foreach ($figureColumns as $fc) {
                    $row[] = $fc === $col ? 1 : '';
                    if ($fc === $col) {
                        $sums[$fc]++;
                    }
                }
                $row[] = 1;
                $rows[] = $row;
            }
            usort($rows, fn ($a, $b) => strcmp((string) $a[0], (string) $b[0]));
            $totalRow = ['TOTAL'];
            for ($i = 2; $i <= $depth; $i++) {
                $totalRow[] = '';
            }
            foreach ($figureColumns as $fc) {
                $totalRow[] = $sums[$fc] === 0 ? '' : $sums[$fc];
            }
            $totalRow[] = $data->count();

            return [...$headers, ...$rows, $totalRow];
        }

        // Partners / Farmers: one row per record with a name column
        $nameHeader = $this->entityNameHeader($entityType);
        $headers = $strategy->figuresHeaders($depth, 'accreditations');

        $rows = [];
        $grandSums = array_fill_keys($figureColumns, 0);
        foreach ($data as $entity) {
            $address = $addresses[$entity->id] ?? null;
            $row = [
                ...$this->entityLocationValues($entity, $address, $entityType, $depth),
                $this->entityName($entity, $entityType),
            ];
            $col = $getEntityFigureColumn($entity);
            foreach ($figureColumns as $fc) {
                $row[] = $fc === $col ? 1 : '';
                if ($fc === $col) {
                    $grandSums[$fc]++;
                }
            }
            $row[] = 1;
            $rows[] = $row;
        }

        $figureValues = [];
        foreach ($figureColumns as $fc) {
            $figureValues[] = $grandSums[$fc] === 0 ? '' : $grandSums[$fc];
        }
        $figureValues[] = $data->count();

        return [...$headers, ...$rows, $this->figuresTotalRow($depth, $nameHeader, $figureValues)];
    }

    private function generateDateAssistedFiguresOutput(Collection $data, int $depth): array
    {
        $strategy = $this->getStrategy();
        $entityType = $strategy->entityType();
        $headers = $strategy->figuresHeaders($depth, 'date-assisted');

        $ids = $data->pluck('id')->unique()->values()->all();
        $addresses = DirectoryLocationResolver::resolveAddresses($entityType, $ids);

        $groups = [];
        $grandTotal = 0;
        foreach ($data as $entity) {
            $address = $addresses[$entity->id] ?? null;
            $locationValues = [];
            $locationValues[] = $entityType === 'center' ? $entity->code ?? '' : $entity->center_code ?? '';
            if ($depth >= 2) {
                $locationValues[] = $this->labelRegion($address->region ?? null);
            }
            if ($depth >= 3) {
                $locationValues[] = $address->province ?? '';
            }
            if ($depth >= 4) {
                $locationValues[] = $address->city ?? '';
            }
            if ($depth >= 5) {
                $locationValues[] = $this->labelDistrict($address->district ?? null);
            }

            // Resolve date assisted value
            $dateStr = '';
            if ($entityType === 'farmer') {
                $raw = $entity->date_assisted ?? null;
                if ($raw !== null && $raw !== '') {
                    try {
                        $dateStr = $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d') : Date::parse((string) $raw)->toDateString();
                    } catch (\Exception $e) {
                        $dateStr = (string) $raw;
                    }
                } else {
                    // Fallback to is_assisted for backward compat when no date
                    $isAssisted = (bool) ($entity->is_assisted ?? false);
                    $metadata = DirectoryMetadata::normalize($entity->metadata ?? '{}');
                    $metaDate = DirectoryMetadata::get($metadata, 'date_assisted', '');
                    if ($metaDate !== '' && $metaDate !== null) {
                        try {
                            $dateStr = Date::parse((string) $metaDate)->toDateString();
                        } catch (\Exception $e) {
                            $dateStr = (string) $metaDate;
                        }
                    } else {
                        $dateStr = $isAssisted ? 'Yes' : 'No';
                        // If neither date nor assisted, show No Date for grouping
                        if (! $isAssisted && $dateStr === 'No') {
                            $dateStr = 'No Date';
                        }
                    }
                }
            } elseif ($entityType === 'center') {
                // Use date_assisted column for centers, fallback to metadata
                $raw = $entity->date_assisted ?? null;
                if ($raw === null || $raw === '') {
                    $metadata = DirectoryMetadata::normalize($entity->metadata ?? '{}');
                    $raw = DirectoryMetadata::get($metadata, 'date_assisted', '');
                }
                if ($raw !== '' && $raw !== null) {
                    try {
                        if ($raw instanceof \DateTimeInterface) {
                            $dateStr = $raw->format('Y-m-d');
                        } else {
                            $dateStr = Date::parse((string) $raw)->toDateString();
                        }
                    } catch (\Exception $e) {
                        $dateStr = (string) $raw;
                    }
                } else {
                    $dateStr = 'No Date';
                }
            } else {
                // Partner: use date_assisted column, fallback to metadata
                $raw = $entity->date_assisted ?? null;
                if ($raw === null || $raw === '') {
                    $metadata = DirectoryMetadata::normalize($entity->metadata ?? '{}');
                    $raw = DirectoryMetadata::get($metadata, 'date_assisted', '');
                }
                if ($raw !== '' && $raw !== null) {
                    try {
                        $dateStr = $raw instanceof \DateTimeInterface ? $raw->format('Y-m-d') : Date::parse((string) $raw)->toDateString();
                    } catch (\Exception $e) {
                        $dateStr = (string) $raw;
                    }
                } else {
                    $dateStr = 'No Date';
                }
            }

            // Partners/Farmers list one row per record (name column), centers aggregate per area+date
            if ($entityType !== 'center') {
                $rows[] = [
                    ...$locationValues,
                    $this->entityName($entity, $entityType),
                    $dateStr,
                    1,
                ];
                $grandTotal++;

                continue;
            }

            $keyValues = array_merge($locationValues, [$dateStr]);
            $key = serialize($keyValues);
            if (! isset($groups[$key])) {
                $groups[$key] = ['values' => $keyValues, 'count' => 0];
            }
            $groups[$key]['count']++;
            $grandTotal++;
        }

        if ($entityType !== 'center') {
            $nameHeader = $this->entityNameHeader($entityType);
            $headers = $strategy->figuresHeaders($depth, 'date-assisted');

            return [
                ...$headers,
                ...$rows,
                $this->figuresTotalRow($depth, $nameHeader, ['', $grandTotal]),
            ];
        }

        $rows = [];
        foreach ($groups as $group) {
            $rows[] = array_merge($group['values'], [$group['count']]);
        }
        usort($rows, fn ($a, $b) => strcmp(implode('|', $a), implode('|', $b)));

        $totalRow = ['TOTAL'];
        for ($i = 2; $i <= $depth; $i++) {
            $totalRow[] = '';
        }
        $totalRow[] = ''; // DATE ASSISTED column blank for total
        $totalRow[] = $grandTotal;

        return [...$headers, ...$rows, $totalRow];
    }

    private function generateProductsFiguresOutput(Collection $data, int $depth): array
    {
        $strategy = $this->getStrategy();
        $entityType = $strategy->entityType();
        $headers = $strategy->figuresHeaders($depth, 'products');

        $ids = $data->pluck('id')->unique()->values()->all();
        $addresses = DirectoryLocationResolver::resolveAddresses($entityType, $ids);

        // Resolve products per entity via BDP production tables (milk/meat/manure) — pivoted per product
        if ($entityType === 'center') {
            $productsMap = DirectoryBdpProductResolver::getForCenters($ids);
        } elseif ($entityType === 'partner') {
            $productsMap = DirectoryBdpProductResolver::getForPartners($ids);
        } else {
            $productsMap = DirectoryBdpProductResolver::getForFarmers($ids);
        }

        // Product columns in example order: Milk, Manure, Meat
        $productKeys = ['milk', 'manure', 'meat'];

        $grandSums = array_fill_keys($productKeys, 0);
        $grandTotal = 0;

        // Centers: aggregated per center (one row per center); Partners/Farmers: per-entity row with name column
        if ($entityType === 'center') {
            $groups = [];
            foreach ($data as $entity) {
                $address = $addresses[$entity->id] ?? null;
                $locationValues = [];
                $locationValues[] = $entity->code ?? '';
                if ($depth >= 2) {
                    $locationValues[] = $this->labelRegion($address->region ?? null);
                }
                if ($depth >= 3) {
                    $locationValues[] = $address->province ?? '';
                }
                if ($depth >= 4) {
                    $locationValues[] = $address->city ?? '';
                }
                if ($depth >= 5) {
                    $locationValues[] = $this->labelDistrict($address->district ?? null);
                }

                $key = serialize($locationValues);
                if (! isset($groups[$key])) {
                    $groups[$key] = ['values' => $locationValues, 'counts' => array_fill_keys($productKeys, 0), 'total' => 0];
                }

                $cats = $productsMap[$entity->id] ?? [];
                $catsLower = array_map(fn ($c) => strtolower(trim($c)), $cats);
                $hasAny = count(array_intersect($catsLower, $productKeys)) > 0;
                foreach ($productKeys as $pk) {
                    if (in_array($pk, $catsLower, true)) {
                        $groups[$key]['counts'][$pk]++;
                        $grandSums[$pk]++;
                    }
                }
                if ($hasAny) {
                    $groups[$key]['total']++;
                    $grandTotal++;
                }
            }

            $rows = [];
            foreach ($groups as $group) {
                $row = $group['values'];
                foreach ($productKeys as $pk) {
                    $row[] = $group['counts'][$pk] === 0 ? '' : $group['counts'][$pk];
                }
                $row[] = $group['total'] === 0 ? '' : $group['total'];
                $rows[] = $row;
            }
            usort($rows, fn ($a, $b) => strcmp(implode('|', $a), implode('|', $b)));

            $totalRow = ['TOTAL'];
            for ($i = 2; $i <= $depth; $i++) {
                $totalRow[] = '';
            }
            foreach ($productKeys as $pk) {
                $totalRow[] = $grandSums[$pk] === 0 ? '' : $grandSums[$pk];
            }
            $totalRow[] = $grandTotal === 0 ? '' : $grandTotal;

            return [...$headers, ...$rows, $totalRow];
        }

        // Partners / Farmers: one row per entity with name column (PARTNER NAME / FARMER NAME)
        // TOTAL only counts involved entities (has at least one of Milk/Manure/Meat)
        $rows = [];
        foreach ($data as $entity) {
            $address = $addresses[$entity->id] ?? null;
            $row = [
                ...$this->entityLocationValues($entity, $address, $entityType, $depth),
                $this->entityName($entity, $entityType),
            ];

            $cats = $productsMap[$entity->id] ?? [];
            $catsLower = array_map(fn ($c) => strtolower(trim($c)), $cats);
            $hasAny = count(array_intersect($catsLower, $productKeys)) > 0;
            foreach ($productKeys as $pk) {
                $has = in_array($pk, $catsLower, true);
                $row[] = $has ? 1 : '';
                if ($has) {
                    $grandSums[$pk]++;
                }
            }
            $row[] = $hasAny ? 1 : '';
            $rows[] = $row;
            if ($hasAny) {
                $grandTotal++;
            }
        }

        usort($rows, fn ($a, $b) => strcmp(implode('|', $a), implode('|', $b)));

        // TOTAL row: single TOTAL label + blanks for remaining location cols + name col + per-product sums + grand total
        $totalValues = [];
        foreach ($productKeys as $pk) {
            $totalValues[] = $grandSums[$pk] === 0 ? '' : $grandSums[$pk];
        }
        $totalValues[] = $grandTotal === 0 ? '' : $grandTotal;

        return [
            ...$headers,
            ...$rows,
            $this->figuresTotalRow($depth, $this->entityNameHeader($entityType), $totalValues),
        ];
    }

    private function getEntityPrograms(object $entity, string $entityType): array
    {
        if ($entityType === 'farmer') {
            $raw = $entity->all_programs
                ?? $entity->profile_project_category
                ?? $entity->project_category
                ?? '';
            $codes = is_array($raw) ? $raw : explode(',', (string) $raw);

            return array_values(array_filter(array_map(fn ($code) => trim((string) $code), $codes), fn ($code) => $code !== ''));
        }

        $programs = (string) ($entity->all_programs ?? $entity->program ?? '');
        if ($programs === '') {
            return [];
        }

        $codes = [];
        foreach (explode(',', $programs) as $program) {
            $program = trim($program);
            if ($program !== '') {
                $codes[] = $program;
            }
        }

        return $codes;
    }

    private function programLabel(string $code): string
    {
        $normalized = strtoupper(trim($code));

        return match ($normalized) {
            'CHB', 'CHBU' => 'CHB',
            'EPAHP', 'EP-AHP', 'EP AHP' => 'EPAHP',
            'CBED', 'CBIN', 'CCDP', 'PL480' => $normalized,
            default => '',
        };
    }

    private function labelRegion(mixed $region): string
    {
        if ($region === null || trim((string) $region) === '') {
            return '';
        }

        return AddressRegionEnum::tryFroMatches((string) $region)?->label() ?? (string) $region;
    }

    private function labelDistrict(mixed $district): string
    {
        if ($district === null || trim((string) $district) === '') {
            return '';
        }

        $value = trim((string) $district);
        $enum = AddressDistrictEnum::tryFroMatches($value);

        return $enum instanceof AddressDistrictEnum ? AddressDistrictEnum::ordinals()[$enum->value] ?? $value : $value;
    }

    private function labelStatus(mixed $status): string
    {
        $value = trim((string) $status);
        if ($value === '') {
            return '';
        }

        return ProfileStatusEnum::tryFrom(strtolower($value))?->label() ?? $value;
    }

    private function getStrategy(): DirectoryEntityStrategy
    {
        return match ($this->template) {
            'centers' => new CentersDirectoryStrategy,
            'partners' => new PartnersDirectoryStrategy,
            'farmers' => new FarmersDirectoryStrategy,
            default => new CentersDirectoryStrategy,
        };
    }

    private function entityNameHeader(string $entityType): string
    {
        return $entityType === 'farmer' ? 'FARMER NAME' : 'PARTNER NAME';
    }

    private function entityName(object $entity, string $entityType): string
    {
        if ($entityType === 'partner') {
            return trim((string) ($entity->name ?? ''));
        }

        return trim(implode(' ', array_filter([
            $entity->first_name ?? null,
            $entity->middle_name ?? null,
            $entity->last_name ?? null,
        ], static fn ($part): bool => $part !== null && trim((string) $part) !== '')));
    }

    private function figuresLocationHeaders(int $depth): array
    {
        $headers = [];
        if ($depth >= 1) {
            $headers[] = 'CENTER';
        }
        if ($depth >= 2) {
            $headers[] = 'REGION';
        }
        if ($depth >= 3) {
            $headers[] = 'PROVINCE';
        }
        if ($depth >= 4) {
            $headers[] = 'MUNICIPALITY';
        }
        if ($depth >= 5) {
            $headers[] = 'District';
        }

        return $headers;
    }

    private function entityLocationValues(object $entity, ?object $address, string $entityType, int $depth): array
    {
        $values = [$entityType === 'center' ? ($entity->code ?? '') : ($entity->center_code ?? '')];
        if ($depth >= 2) {
            $values[] = $this->labelRegion($address->region ?? null);
        }
        if ($depth >= 3) {
            $values[] = $address->province ?? '';
        }
        if ($depth >= 4) {
            $values[] = $address->city ?? '';
        }
        if ($depth >= 5) {
            $values[] = $this->labelDistrict($address->district ?? null);
        }

        return $values;
    }

    private function figuresTotalRow(int $depth, ?string $nameHeader, array $figureValues): array
    {
        $totalRow = ['TOTAL'];
        for ($i = 2; $i <= $depth; $i++) {
            $totalRow[] = '';
        }
        if ($nameHeader !== null) {
            $totalRow[] = '';
        }

        return [...$totalRow, ...$figureValues];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A'.(self::HEADER_ROW_COUNT + 1));
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $totalRows = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();
        $headerRows = self::HEADER_ROW_COUNT;

        $styles = [
            "A1:{$lastColumn}{$totalRows}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];

        for ($i = 1; $i <= $headerRows; $i++) {
            $styles["A{$i}:{$lastColumn}{$i}"] = [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'f0f0f0'],
                ],
            ];
        }

        return $styles;
    }
}
