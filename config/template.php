<?php

/*
|--------------------------------------------------------------------------
| Report Templates
|--------------------------------------------------------------------------
|
| Named column layouts used when rendering/exporting reports. Each template
| lists the output columns and how they are sourced, so the report UI and
| export layer stay in sync without hardcoding column lists.
|
*/

// pivot layout values commented for now - hide pivot layout
$templates = [
    'date-annual' => [
        'label' => 'Date (Annual)',
        // 'axis' => [
        //     'time_granularity' => 'year',
        //     'scope_breakdown' => 'none',
        // ],
        'rows' => ['year'],
        'columns' => [
            ['key' => 'year', 'label' => 'YEAR'],
            ['key' => 'metric', 'label' => 'MILK PRODUCTION'],
        ],
        'grand_total' => true,
        'variants' => [
            'year-product' => [
                'label' => 'Year & Product',
                'columns' => [
                    ['key' => 'year', 'label' => 'YEAR'],
                    ['key' => 'metric', 'label' => 'MILK PRODUCTION'],
                ],
            ],
            'center-partner' => [
                'label' => 'By Center and Partner',
                'columns' => [
                    ['key' => 'year', 'label' => 'YEAR'],
                    ['key' => 'center', 'label' => 'Center'],
                    ['key' => 'partner', 'label' => 'Partner'],
                    ['key' => 'grand_total', 'label' => 'Grand Total'],
                ],
            ],
            'by-center-years' => [
                'label' => 'By Center (Years as Columns)',
                'columns' => [
                    ['key' => 'center', 'label' => 'CENTER'],
                    ['key' => 'region', 'label' => 'REGION'],
                    ['key' => 'years', 'label' => 'YEARS'],
                    ['key' => 'total', 'label' => 'TOTAL'],
                ],
            ],
            'center-partner-by-year' => [
                'label' => 'Center & Partner by Year',
                'columns' => [
                    ['key' => 'center', 'label' => 'CENTER'],
                    ['key' => 'yearly_center_partner', 'label' => 'YEARLY BREAKDOWN'],
                    ['key' => 'grand_total', 'label' => 'GRAND TOTAL'],
                ],
            ],
        ],
    ],
    'date-monthly' => [
        'label' => 'Date (Monthly)',
        'rows' => ['year'],
        'columns' => [
            ['key' => 'year', 'label' => 'YEAR'],
            ['key' => 'january', 'label' => 'January'],
            ['key' => 'february', 'label' => 'February'],
            ['key' => 'march', 'label' => 'March'],
            ['key' => 'april', 'label' => 'April'],
            ['key' => 'may', 'label' => 'May'],
            ['key' => 'june', 'label' => 'June'],
            ['key' => 'july', 'label' => 'July'],
            ['key' => 'august', 'label' => 'August'],
            ['key' => 'september', 'label' => 'September'],
            ['key' => 'october', 'label' => 'October'],
            ['key' => 'november', 'label' => 'November'],
            ['key' => 'december', 'label' => 'December'],
            ['key' => 'total', 'label' => 'TOTAL'],
        ],
        'grand_total' => true,
        'variants' => [
            'by-month' => [
                'label' => 'By Month',
                'columns' => [
                    ['key' => 'year', 'label' => 'YEAR'],
                    ['key' => 'january', 'label' => 'January'],
                    ['key' => 'february', 'label' => 'February'],
                    ['key' => 'march', 'label' => 'March'],
                    ['key' => 'april', 'label' => 'April'],
                    ['key' => 'may', 'label' => 'May'],
                    ['key' => 'june', 'label' => 'June'],
                    ['key' => 'july', 'label' => 'July'],
                    ['key' => 'august', 'label' => 'August'],
                    ['key' => 'september', 'label' => 'September'],
                    ['key' => 'october', 'label' => 'October'],
                    ['key' => 'november', 'label' => 'November'],
                    ['key' => 'december', 'label' => 'December'],
                    ['key' => 'total', 'label' => 'TOTAL'],
                ],
            ],
        ],
    ],
    // Directory — same source as reports.directory, now in template.php for consistency (BDP + directory share template.php)
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
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default template
    |--------------------------------------------------------------------------
    |
    | The layout applied when a report does not specify its own template.
    |
    */
    'default' => null,

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | Template definitions keyed by template identifier. Every template has:
    |
    |   label   — display name for the template selector.
    |   axis    — which `time_granularity` (year|month) and `scope_breakdown`
    |             (none|combined_columns|separate_tables) presets from the
    |             report definition drive this layout; area templates instead
    |             set `rows: area` with `area_depth` and `area_breakdown`.
    |   rows    — fields each row is grouped by (e.g. year).
    |   columns — fixed output columns; `key` is the data key, `label` is
    |             the column header.
    |   pivot   — optional month/period pivot: `by` is the period key and
    |             `columns` lists the period buckets turned into columns.
    |   blocks  — optional separate table blocks (one per scope element)
    |             used when `scope_breakdown` is `separate_tables`.
    |   grand_total — whether to append a grand total row/column.
    |
    */
    'templates' => $templates,

];
