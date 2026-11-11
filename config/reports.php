<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reports Registry
    |--------------------------------------------------------------------------
    |
    | Central configuration for all report definitions. Each key is a report
    | identifier used by the report engine (e.g. the "bdp" Buffalo Derived
    | Products report). The config covers labels, filter field definitions,
    | data-source tables, and axis presets only — resolver wiring stays in
    | the enums.
    |
    */

    'bdp' => [

        /*
        |------------------------------------------------------------------
        | Display label
        |------------------------------------------------------------------
        |
        | Human-readable name shown in the report UI and exported documents.
        |
        */
        'label' => 'Buffalo Derived Products Report',

        /*
        |------------------------------------------------------------------
        | Products
        |------------------------------------------------------------------
        |
        | Buffalo derived products selectable as the report's primary axis.
        | Values map to ProductCategoryEnum. Products without a data source
        | (e.g. Hide) are disabled in the UI.
        |
        */
        'products' => [
            'milk' => 'Milk',
            'meat' => 'Meat',
            'manure' => 'Manure',
            'hide' => 'Hide',
        ],

        /*
        |------------------------------------------------------------------
        | Report types
        |------------------------------------------------------------------
        |
        | Whether the report measures production or sales. Values map to
        | ReportTypeEnum.
        |
        */
        'types' => [
            'production' => 'Production',
            'sales' => 'Sales',
        ],

        /*
        |------------------------------------------------------------------
        | Data sources
        |------------------------------------------------------------------
        |
        | For each product/type combination, the database table and the
        | metric column that is aggregated. A `null` source means the
        | combination has no data yet and the resolver returns no rows.
        |
        */
        'sources' => [
            'milk' => [
                'production' => ['table' => 'milk_productions', 'metric' => 'total_volume_produced'],
                'sales' => ['table' => 'milk_sales', 'metric' => 'total_volume_sold'],
            ],
            'meat' => [
                'production' => ['table' => 'meat_productions', 'metric' => 'total_volume'],
                'sales' => ['table' => 'meat_sales', 'metric' => 'total_sales'],
            ],
            'manure' => [
                'production' => ['table' => 'manure_productions', 'metric' => 'total_volume'],
                'sales' => ['table' => 'manure_sales', 'metric' => 'total_sales'],
            ],
            'hide' => [
                'production' => null,
                'sales' => null,
            ],
        ],

        /*
        |------------------------------------------------------------------
        | Filter fields
        |------------------------------------------------------------------
        |
        | The filters rendered for this report, in display order. Each key is
        | the query parameter used when generating/exporting the report.
        |
        */
        'filters' => [
            'template',
            'list_and_figures',
            'product',
            'type',
            'year',
            'program',
            'center',
            'partner',
            'farmer',
            'month',
            'psgc_address',
        ],

        /*
        |------------------------------------------------------------------
        | Filter element overrides
        |------------------------------------------------------------------
        |
        | The region/province/municipality/district address drill-down is
        | rendered as a single PSGC cascade (Region → Province → Municipality
        | → District) sourced from the official PSGC data.
        |
        */
        'elements' => [
            'psgc_address' => 'psgcAddress',
            // Partners/Farmers listed one-per-row via the `partner`/`farmer`
            // filters (PARTNER/FARMER NAME breakdown), bound to
            // `filter.partner`/`filter.farmer` instead of `filter.partner_id`/
            // `filter.farmer_id`. Shared across BDP and Directory.
            'partner' => 'selectPartners',
            'farmer' => 'selectFarmers',
        ],

        /*
        |------------------------------------------------------------------
        | Defaults
        |------------------------------------------------------------------
        |
        | Default values applied when a filter is not supplied.
        |
        */
        'defaults' => [
            'template' => null,
            'list_and_figures' => null,
            'product' => 'milk',
            'type' => 'production',
            'year' => null,
            'program' => null,
            'center' => null,
            'month' => null,
            'region' => null,
            'province' => null,
            'municipality' => null,
        ],

        // pivot layout - axis presets commented for now (hide pivot layout)
        // 'axis' => [
        //     'time_granularity' => [
        //         'year',
        //         'month',
        //     ],
        //     'scope_breakdown' => [
        //         'none',
        //         'combined_columns',
        //         'separate_tables',
        //     ],
        // ],

        /*
        |------------------------------------------------------------------
        | Address hierarchy
        |------------------------------------------------------------------
        |
        | Geographic drill-down order for the "By Area" grouping. Values map
        | to columns on the address records (region → province → district →
        | municipality/city).
        |
        */
        'address' => [
            'region',
            'province',
            'municipality',
            'district',
        ],

    ],

    'directory' => [

        /*
        |------------------------------------------------------------------
        | Display label
        |------------------------------------------------------------------
        |
        | Human-readable name shown in the report UI and exported documents.
        |
        */
        'label' => 'Directory Data Report',

        /*
        |------------------------------------------------------------------
        | Templates
        |------------------------------------------------------------------
        |
        | Directory templates with variants. Mirrors BDP's use of
        | config/template.php but kept here so `reports.directory` is the
        | single source of truth for directory. Each template has an `all-data`
        | variant (All Data) — future variants slot in here without code changes.
        |
        */
        'templates' => [
            'centers' => [
                'label' => 'Centers',
                'variants' => [
                    'all-data' => ['label' => 'All Data (Centers)'],
                    'status' => ['label' => 'Status'],
                    'program' => ['label' => 'Program'],
                    'accreditations' => ['label' => 'Accreditations'],
                    'date-assisted' => ['label' => 'Date Assisted'],
                    'products' => ['label' => 'Buffalo Derived Products'],
                ],
            ],
            'partners' => [
                'label' => 'Partners',
                'variants' => [
                    'all-data' => ['label' => 'All Data (Partners)'],
                    'status' => ['label' => 'Status'],
                    'program' => ['label' => 'Program'],
                    'accreditations' => ['label' => 'Accreditations'],
                    'date-assisted' => ['label' => 'Date Assisted'],
                    'products' => ['label' => 'Buffalo Derived Products'],
                ],
            ],
            'farmers' => [
                'label' => 'Farmers',
                'variants' => [
                    'all-data' => ['label' => 'All Data (Farmers)'],
                    'status' => ['label' => 'Status'],
                    'program' => ['label' => 'Program'],
                    'accreditations' => ['label' => 'Accreditations'],
                    'date-assisted' => ['label' => 'Date Assisted'],
                    'products' => ['label' => 'Buffalo Derived Products'],
                ],
            ],
        ],

        /*
        |------------------------------------------------------------------
        | Masterlist types
        |------------------------------------------------------------------
        |
        | The record types included in the directory. 'all' exports every
        | type as its own sheet; a specific type exports only that sheet.
        |
        */
        'types' => [
            'centers' => 'Centers',
            'partners' => 'Partners',
            'farmers' => 'Farmers',
        ],

        /*
        |------------------------------------------------------------------
        | Filter fields
        |------------------------------------------------------------------
        |
        | The filters rendered for this report, in display order. Each key is
        | the query parameter used when generating/exporting the report.
        |
        */
        'filters' => [
            'template',
            'list_and_figures',
            'partner_category',
            'center',
            'partner',
            'farmer',
            'status',
            'project',
            'accreditation',
            'psgc_address',
        ],

        /*
        |------------------------------------------------------------------
        | Filter element overrides
        |------------------------------------------------------------------
        |
        | Maps filter fields to the FiltersPanel element rendered for them,
        | overriding the shared defaults in ReportCatalogService.
        | Aligned with BDP so Center/Partner/Farmer dropdowns share the same
        | UI component (selectPartners/selectFarmers bound to filter.partner/
        | filter.farmer) and filtering logic (center → partner → farmer cascade).
        |
        */
        'elements' => [
            'psgc_address' => 'psgcAddress',
            'partner' => 'selectPartners',
            'farmer' => 'selectFarmers',
        ],

        /*
        |------------------------------------------------------------------
        | Defaults
        |------------------------------------------------------------------
        |
        | Default values applied when a filter is not supplied.
        |
        */
        'defaults' => [
            'template' => 'centers',
            'list_and_figures' => 'list',
            'partner_category' => null,
            'center' => null,
            'partner' => null,
            'region' => null,
            'province' => null,
            'city' => null,
            'district' => null,
            'status' => null,
            'project' => null,
            'accreditation' => null,
        ],

        /*
        |------------------------------------------------------------------
        | Address hierarchy
        |------------------------------------------------------------------
        |
        | Geographic drill-down order for the "By Area" grouping. Values map
        | to columns on the address records (region → province → district →
        | municipality/city).
        |
        */
        'address' => [
            'region',
            'province',
            'municipality',
            'district',
        ],

    ],

    'custom-pivot' => [

        /*
        |------------------------------------------------------------------
        | Display label
        |------------------------------------------------------------------
        |
        | Human-readable name shown in the report UI. The custom pivot has no
        | config-driven filters; it renders the drag-and-drop pivot builder.
        |
        */
        'label' => 'Random Generate Report',

        /*
        |------------------------------------------------------------------
        | Filter fields
        |------------------------------------------------------------------
        |
        | The pivot builder manages its own zones, so no filters are exposed
        | to the shared FiltersPanel.
        |
        */
        'filters' => [],

    ],

];
