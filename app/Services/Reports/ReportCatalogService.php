<?php

namespace App\Services\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Single source of truth for the report catalog exposed to the Generate
 * Report UI. Reads config/reports.php (report definitions) and
 * config/template.php (output templates).
 *
 * Config-driven reports declare their filters as query parameter names in
 * config/reports.php; this service maps them to the FiltersPanel element
 * keys the frontend renders. Reports that have not yet been migrated to
 * config are kept here as legacy entries so the UI keeps working.
 */
class ReportCatalogService
{
    /**
     * Maps report filter field keys (config/reports.php `filters`) to the
     * FiltersPanel element keys rendered on the frontend.
     *
     * @var array<string, string>
     */
    private const FILTER_ELEMENT_MAP = [
        'template' => 'reportTemplate',
        'list_and_figures' => 'listAndFigures',
        'product' => 'product',
        'type' => 'reportType',
        'year' => 'years',
        'center' => 'selectCenters',
        'month' => 'months',
        'region' => 'region',
        'province' => 'provinces',
        'municipality' => 'cities',
        'city' => 'cities',
        'district' => 'district',
        'program' => 'programs',
        'partner' => 'partners',
        'farmer' => 'farmers',
        'status' => 'status',
        'project' => 'projects',
        'partner_category' => 'partnerType',
        'business_type' => 'partnerType',
        'accreditation' => 'accreditation',
    ];

    /**
     * Reports not yet migrated to config/reports.php. Their filter elements
     * are already FiltersPanel element keys.
     *
     * @var array<string, array{label: string, elements: array<int, string>}>
     */
    private const LEGACY_REPORTS = [
        'milk-feeding-programs' => [
            'label' => 'Milk Feeding Programs Report',
            'elements' => [
                'years',
                'fundedBy',
                'selectCenters',
                'modeOfProcurement',
                'suppliers',
                'psgcAddress',
            ],
        ],
        'export-template' => [
            'label' => 'Supreme Template',
            'elements' => [
                'templateFile',
            ],
        ],
    ];

    /**
     * All report types as [{value, label}], config-driven reports first.
     */
    public function reportTypes(): array
    {
        return $this->allReports()
            ->map(fn (array $definition, string $key) => [
                'value' => $key,
                'label' => $definition['label'] ?? Str::headline($key),
            ])
            ->values()
            ->all();
    }

    /**
     * The FiltersPanel element keys to render for a report type.
     */
    public function filterElements(string $reportType): array
    {
        if (isset(self::LEGACY_REPORTS[$reportType])) {
            return self::LEGACY_REPORTS[$reportType]['elements'];
        }

        $map = array_merge(
            self::FILTER_ELEMENT_MAP,
            config("reports.{$reportType}.elements", [])
        );

        return collect(config("reports.{$reportType}.filters", []))
            ->map(fn (string $filter) => $map[$filter] ?? $filter)
            ->values()
            ->all();
    }

    /**
     * Filter elements for every report type, keyed by report type.
     */
    public function filterElementsByType(): array
    {
        return collect($this->reportTypes())
            ->mapWithKeys(fn (array $type) => [
                $type['value'] => $this->filterElements($type['value']),
            ])
            ->all();
    }

    /**
     * Output templates as [{value, label}] from config/template.php.
     */
    public function templates(): array
    {
        return collect(config('template.templates', []))
            ->map(fn (array $template, string $key) => [
                'value' => $key,
                'label' => $template['label'] ?? Str::headline($key),
            ])
            ->values()
            ->all();
    }

    /**
     * The raw definition for a single report type (config or legacy).
     */
    public function report(string $reportType): ?array
    {
        return $this->allReports()->get($reportType);
    }

    /**
     * Config-driven reports merged with the legacy reports not yet migrated.
     */
    private function allReports(): Collection
    {
        return collect(config('reports', []))->merge(self::LEGACY_REPORTS);
    }
}
