<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Tag\CreateTagAction;
use App\Http\Requests\MilkUtilizationRequest;
use App\Queries\Reports\MilkUtilizationQuery;
use App\Support\MilkUtilizationCalculator;
use App\Workflows\ExportMilkUtilizationWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Response as InertiaResponse;

class MilkUtilizationController extends Controller
{
    public function page(MilkUtilizationQuery $query): InertiaResponse
    {
        request()->user()?->loadMissing('center');

        return inertia('MilkUtilization/Index', [
            'options' => $this->options($query),
        ]);
    }

    public function __invoke(
        MilkUtilizationRequest $request,
        MilkUtilizationQuery $query,
        MilkUtilizationCalculator $calculator,
    ): JsonResponse {
        $validated = $request->validated();

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $section = (string) $validated['section'];

        $centerId = $validated['center_id'] ?? $validated['center'] ?? null;
        $centerId = $centerId === null ? null : (int) $centerId;

        $rawRows = $section === 'cbed'
            ? $query->cbed($year, $month, $centerId)
            : $query->institutional($year, $month, $centerId);

        $sectionPayload = $this->buildSection($section, $rawRows, $calculator);

        return response()->json([
            'filters' => $this->filters($year, $month, $centerId),
            'columns' => $this->columns($section),
            'group_headers' => array_values((array) config('milk_utilization.group_headers', [])),
            'sections' => [$sectionPayload],
            'warnings' => $sectionPayload['warnings'],
            'report_colors' => $this->reportColors(),
            'legend' => array_values((array) config('milk_utilization.legend', [])),
            'notes' => array_values((array) config('milk_utilization.notes', [])),

            'source_status_notes' => (array) config('milk_utilization.source_status_notes', []),
            'options' => $this->options($query, $year),
        ]);
    }

    private function options(MilkUtilizationQuery $query, ?int $year = null): array
    {
        return [
            'years' => collect($query->years())
                ->map(fn (int $value) => ['value' => $value, 'label' => (string) $value])
                ->values()
                ->all(),
            'months' => $query->months(),
            'centers' => $query->centerOptions(),
            'genepool' => $query->genepool(),
            'default_period' => $query->latestPeriod($year),
        ];
    }

    private function buildSection(string $sectionKey, array $rawRows, MilkUtilizationCalculator $calculator): array
    {
        $definition = (array) config("milk_utilization.sections.{$sectionKey}", []);

        $rows = [];
        $rowValues = [];
        $warnings = [];

        foreach ($rawRows as $rawRow) {
            $values = $calculator->calculateRow($rawRow['raw']);

            $rowValues[] = $values;
            $warnings = array_merge($warnings, $calculator->warnings($sectionKey, $rawRow['key'], $rawRow['label'], $values));

            $children = [];

            foreach ((array) ($rawRow['children'] ?? []) as $child) {
                $childValues = $calculator->calculateRow($child['raw']);

                $warnings = array_merge($warnings, $calculator->warnings($sectionKey, $child['key'], $child['label'], $childValues));

                $children[] = [
                    'key' => $child['key'],
                    'label' => $child['label'],
                    'kind' => $child['kind'],
                    'is_genepool' => false,
                    'has_children' => false,
                    'values' => $childValues,
                ];
            }

            $rows[] = [
                'key' => $rawRow['key'],
                'label' => $rawRow['label'],
                'kind' => $rawRow['kind'],
                'is_genepool' => $rawRow['is_genepool'],
                'has_children' => $rawRow['has_children'],
                'values' => $values,
                'children' => $children,
            ];
        }

        $totalValues = $calculator->calculateTotal($rowValues);
        $totalLabel = (string) ($definition['total_label'] ?? 'Total');

        $totalKey = $sectionKey === 'cbed' ? 'GRAND_TOTAL' : 'TOTAL';

        $warnings = array_merge($warnings, $calculator->warnings($sectionKey, $totalKey, $totalLabel, $totalValues));

        return [
            'key' => $sectionKey,
            'title' => $definition['title'] ?? '',
            'marker' => $definition['marker'] ?? '',
            'note' => $definition['note'] ?? '',
            'layout' => $definition['layout'] ?? 'flat',
            'color_key' => $definition['color_key'] ?? 'title_bar_black',
            'rows' => $rows,
            'total' => [
                'key' => $totalKey,
                'label' => $totalLabel,
                'kind' => 'total',
                'values' => $totalValues,
            ],
            'warnings' => $warnings,
        ];
    }

    private function filters(int $year, int $month, ?int $centerId): array
    {
        $from = Date::create($year, 1, 1)->startOfMonth();
        $to = Date::create($year, $month, 1)->endOfMonth();

        return [
            'year' => $year,
            'month' => $month,
            'center_id' => $centerId,
            'coverage_label' => 'Coverage: '.$from->format('M').' to '.$to->format('F Y'),
            'coverage_from' => $from->toDateString(),
            'coverage_to' => $to->toDateString(),
        ];
    }

    public function export(
        Request $request,
        ExportMilkUtilizationWorkflow $exportMilkUtilizationWorkflow,
        CreateDocumentAction $createDocumentAction,
        CreateTagAction $createTagAction,
    ) {
        $validated = $request->validate([
            'format' => ['required', 'string', 'in:xlsx,csv,pdf'],
            'queue_export' => ['nullable', 'boolean'],
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer'],
            'section' => ['required', 'string', 'in:institutional,cbed'],
            'center_id' => ['nullable', 'integer'],
        ]);

        $format = $validated['format'];
        $queueExport = (bool) ($validated['queue_export'] ?? true);
        $year = $validated['year'];
        $month = $validated['month'];
        $section = $validated['section'];
        $centerId = $validated['center_id'] ?? null;

        $query = new MilkUtilizationQuery;
        $calculator = new MilkUtilizationCalculator;

        $data = $section === 'institutional'
            ? $query->institutional($year, $month, $centerId)
            : $query->cbed($year, $month, $centerId);

        $definition = (array) config("milk_utilization.sections.{$section}", []);
        $allColumns = (array) config('milk_utilization.columns', []);

        $labelColumn = $allColumns['row_label'] ?? null;
        unset($allColumns['row_label']);

        $columns = array_values(array_filter($allColumns, fn ($col) => ($col['in_values'] ?? false) === true));

        $rows = [];

        $from = Date::create($year, 1, 1)->format('F');
        $to = Date::create($year, $month, 1)->format('F Y');
        $title = $definition['title'] ?? '';
        $rows[] = ["{$title} — Coverage: {$from} to {$to}"];

        $rows[] = array_merge(
            [$labelColumn['label'] ?? 'Center'],
            array_column($columns, 'label'),
        );

        $sectionPayload = $this->buildSection($section, $data, $calculator);

        foreach ($sectionPayload['rows'] as $row) {
            $rows[] = $this->exportRow($row['label'], $row['values'], $columns);

            foreach ($row['children'] as $child) {
                $rows[] = $this->exportRow('    '.$child['label'], $child['values'], $columns);
            }
        }

        $rows[] = $this->exportRow(
            $sectionPayload['total']['label'],
            $sectionPayload['total']['values'],
            $columns,
        );

        if ($queueExport) {
            $document = $exportMilkUtilizationWorkflow->queueExport(
                rows: $rows,
                format: $format,
                createDocumentAction: $createDocumentAction,
                createTagAction: $createTagAction,
            );

            return response()->json([
                'document_id' => $document->id,
            ]);
        }

        return $exportMilkUtilizationWorkflow->handleDirectExport(
            rows: $rows,
            format: $format,
        );
    }

    private function columns(string $sectionKey): array
    {
        $columns = [];

        foreach ((array) config('milk_utilization.columns', []) as $definition) {
            $columns[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'letter' => $definition['letter'],
                'kind' => $definition['kind'],
                'calculation' => $definition['calculation'],
                'formula' => $definition['formula'],
                'unit' => $definition['unit'],
                'source' => $definition['source'],
                'source_status' => $definition['source_status'],
                'number_format' => $definition['number_format'],
                'decimals' => $definition['decimals'],
                'color_key' => $definition['color_key_by_section'][$sectionKey] ?? $definition['color_key'] ?? 'none',
                'bold' => (bool) ($definition['bold'] ?? false),
                'sensitive' => (bool) ($definition['sensitive'] ?? false),
                'in_values' => (bool) ($definition['in_values'] ?? true),
            ];
        }

        return $columns;
    }

    private function exportRow(string $label, array $values, array $columns): array
    {
        $row = [$label];

        foreach ($columns as $column) {
            $value = $values[$column['key']] ?? null;
            if ($value === null) {
                $decimals = $column['key'] === 'lactating_animals'
                    ? 0
                    : (int) ($column['decimals'] ?? 2);
                $row[] = number_format(0, $decimals, '.', ',');

                continue;
            }

            $decimals = $column['key'] === 'lactating_animals'
                ? 0
                : (int) ($column['decimals'] ?? 2);
            $row[] = number_format((float) $value, $decimals, '.', ',');
        }

        return $row;
    }

    private function reportColors(): array
    {
        return array_map(
            fn ($color) => ['hex' => $color['hex'] ?? null, 'token' => $color['token'] ?? ''],
            (array) config('milk_utilization.colors', []),
        );
    }
}
