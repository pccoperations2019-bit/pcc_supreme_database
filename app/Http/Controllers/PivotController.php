<?php

namespace App\Http\Controllers;

use App\Services\PivotBuilderService;
use App\Services\PivotExportService;
use App\Services\PivotFieldRegistryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PivotController extends Controller
{
    public function __construct(
        private PivotFieldRegistryService $registry,
        private PivotBuilderService $builder
    ) {}

    public function index()
    {
        return Inertia::render('Reports/PivotBuilder', [
            'sources' => $this->registry->sources(),
            'initialSource' => 'center',
        ]);
    }

    public function fields(Request $request)
    {
        $request->validate([
            'source' => [
                'required',
                'string',
            ],
        ]);

        return response()->json(
            $this->registry->fieldsFor(
                $request->string('source')->toString()
            )
        );
    }

    public function build(Request $request)
    {
        $validated = $request->validate([
            'source' => [
                'required',
                'string',
            ],

            'rows' => [
                'array',
            ],

            'columns' => [
                'array',
            ],

            'values' => [
                'array',
            ],

            'filters' => [
                'array',
            ],
        ]);

        return response()->json(
            $this->builder->build($validated)
        );
    }

    public function fieldValues(Request $request)
    {
        $request->validate([
            'source' => ['required', 'string'],
            'field' => ['required', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->registry->valuesFor(
                $request->string('source')->toString(),
                $request->string('field')->toString(),
                $request->input('search')
            )
        );
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'source' => [
                'required',
                'string',
            ],

            'type' => [
                'sometimes',
                'in:list,figures',
            ],

            'rows' => [
                'nullable',
                'string',
            ],

            'columns' => [
                'nullable',
                'string',
            ],

            'values' => [
                'nullable',
                'string',
            ],

            'filters' => [
                'nullable',
                'string',
            ],

            'show_totals' => [
                'nullable',
                'boolean',
            ],
        ]);

        $rows = $validated['rows'] ? explode(',', $validated['rows']) : [];
        $columns = $validated['columns'] ? explode(',', $validated['columns']) : [];
        $values = $validated['values'] ? json_decode($validated['values'], true) : [];
        $filters = $validated['filters'] ? json_decode($validated['filters'], true) : [];

        $buildRequest = [
            'source' => $validated['source'],
            'type' => $validated['type'] ?? 'figures',
            'rows' => $rows,
            'columns' => $columns,
            'values' => $values,
            'filters' => $filters,
            'show_totals' => (bool) ($validated['show_totals'] ?? false),
        ];

        $result = $this->builder->build($buildRequest);

        return app(
            PivotExportService::class
        )->exportGeneric(
            $result,
            $buildRequest
        );
    }
}
