<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PL480 Milk Production</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #000;
            font-size: 14px;
            line-height: 1.5;
            background: #fff;
        }

        .page {
            background: #fff;
            border-radius: 8px;
            padding: 32px 24px;
        }

        /* ── Tables ───────────────────────────────────── */
        .table-card {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .section-gap {
            height: 32px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 13px;
        }

        col.province-col {
            width: 19%;
        }

        col.year-col {
            width: 13.5%;
        }

        thead th {
            background: #f8fafc;
            color: #334155;
            font-weight: 600;
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
            overflow: hidden;
        }

        thead th.group {
            text-align: center;
            white-space: normal;
        }

        thead th.year {
            text-align: center;
        }

        tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            color: #334155;
            font-variant-numeric: tabular-nums;
            overflow: hidden;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        td.province {
            text-align: left;
            font-weight: 500;
            color: #0f172a;
        }

        tr.total td {
            font-weight: 600;
            color: #0f172a;
        }

        /* Input-look cells in the first (input data) table */
        .field {
            display: inline-block;
            min-width: 90px;
            max-width: 100%;
            height: 30px;
            line-height: 28px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
            padding: 0 10px;
            text-align: center;
            font-variant-numeric: tabular-nums;
            color: #0f172a;
        }

        .meta {
            margin-top: 24px;
            font-size: 11px;
            color: #64748b;
        }

        @page {
            size: A4 landscape;
            margin: 12mm;
        }
    </style>
</head>

<body>
    @php
        $fmt = function ($value) {
            $number = (float) ($value ?? 0);
            if (!is_finite($number)) {
                return '0';
            }
            $rounded = round($number * 100) / 100;
            $formatted = number_format($rounded, 2, '.', ',');
            $trimmed = rtrim(rtrim($formatted, '0'), '.');
            return $trimmed === '' || $trimmed === '-0' ? '0' : $trimmed;
        };

        $withoutYears = array_values($selectedWithoutYears ?? []);
        $withYears = array_values($selectedWithYears ?? []);
        $withoutCount = count($withoutYears) ?: 1;
        $withCount = count($withYears) ?: 1;
    @endphp

    <div class="page">
        {{-- ── First table: input data ─────────────────────────── --}}
        <div class="table-card">
            <table>
                <colgroup>
                    <col class="province-col">
                    @foreach ($withoutYears as $year)
                        <col class="year-col">
                    @endforeach
                    @foreach ($withYears as $year)
                        <col class="year-col">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2" style="text-align: left;">Province</th>
                        <th class="group" colspan="{{ $withoutCount }}">
                            ACTUAL MILK PRODUCTION W/OUT PL480 INTERVENTION
                        </th>
                        <th class="group" colspan="{{ $withCount }}">
                            WITH PL480 PROJECT (INPUT DATA)
                        </th>
                    </tr>
                    <tr>
                        @forelse ($withoutYears as $year)
                            <th class="year">{{ $year }}</th>
                        @empty
                            <th class="year">&mdash;</th>
                        @endforelse
                        @forelse ($withYears as $year)
                            <th class="year">{{ $year }}</th>
                        @empty
                            <th class="year">&mdash;</th>
                        @endforelse
                    </tr>
                </thead>
                <tbody>
                    @foreach ($provinces as $province)
                        <tr>
                            <td class="province">{{ $province }}</td>
                            @foreach ($withoutYears as $year)
                                <td>{{ $fmt($computedData[$province][$year] ?? 0) }}</td>
                            @endforeach
                            @if (empty($withoutYears))
                                <td>&mdash;</td>
                            @endif
                            @foreach ($withYears as $year)
                                <td>
                                    @php($inputValue = $inputData[$province][$year] ?? 0)
                                    <span class="field">{{ empty($inputValue) ? '' : $fmt($inputValue) }}</span>
                                </td>
                            @endforeach
                            @if (empty($withYears))
                                <td>&mdash;</td>
                            @endif
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td class="province">TOTAL</td>
                        @foreach ($withoutYears as $year)
                            @php($total = 0)
                            @foreach ($provinces as $province)
                                @php($total += (float) ($computedData[$province][$year] ?? 0))
                            @endforeach
                            <td>{{ $fmt($total) }}</td>
                        @endforeach
                        @if (empty($withoutYears))
                            <td>&mdash;</td>
                        @endif
                        @foreach ($withYears as $year)
                            @php($total = 0)
                            @foreach ($provinces as $province)
                                @php($total += (float) ($inputData[$province][$year] ?? 0))
                            @endforeach
                            <td>{{ $fmt($total) }}</td>
                        @endforeach
                        @if (empty($withYears))
                            <td>&mdash;</td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section-gap"></div>

        {{-- ── Second table: computed data ─────────────────────── --}}
        <div class="table-card">
            <table>
                <colgroup>
                    <col class="province-col">
                    @foreach ($withoutYears as $year)
                        <col class="year-col">
                    @endforeach
                    @foreach ($withYears as $year)
                        <col class="year-col">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2" style="text-align: left;">Province</th>
                        <th class="group" colspan="{{ $withoutCount }}">
                            ACTUAL MILK PRODUCTION W/OUT PL480 INTERVENTION
                        </th>
                        <th class="group" colspan="{{ $withCount }}">
                            WITH PL480 PROJECT (COMPUTE MILK PRODUCTION DATA BASED FROM MILK 4DX REPORT)
                        </th>
                    </tr>
                    <tr>
                        @forelse ($withoutYears as $year)
                            <th class="year">{{ $year }}</th>
                        @empty
                            <th class="year">&mdash;</th>
                        @endforelse
                        @forelse ($withYears as $year)
                            <th class="year">{{ $year }}</th>
                        @empty
                            <th class="year">&mdash;</th>
                        @endforelse
                    </tr>
                </thead>
                <tbody>
                    @foreach ($provinces as $province)
                        <tr>
                            <td class="province">{{ $province }}</td>
                            @foreach ($withoutYears as $year)
                                <td>{{ $fmt($computedData[$province][$year] ?? 0) }}</td>
                            @endforeach
                            @if (empty($withoutYears))
                                <td>&mdash;</td>
                            @endif
                            @foreach ($withYears as $year)
                                <td>{{ $fmt($computedData[$province][$year] ?? 0) }}</td>
                            @endforeach
                            @if (empty($withYears))
                                <td>&mdash;</td>
                            @endif
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td class="province">TOTAL</td>
                        @foreach ($withoutYears as $year)
                            @php($total = 0)
                            @foreach ($provinces as $province)
                                @php($total += (float) ($computedData[$province][$year] ?? 0))
                            @endforeach
                            <td>{{ $fmt($total) }}</td>
                        @endforeach
                        @if (empty($withoutYears))
                            <td>&mdash;</td>
                        @endif
                        @foreach ($withYears as $year)
                            @php($total = 0)
                            @foreach ($provinces as $province)
                                @php($total += (float) ($computedData[$province][$year] ?? 0))
                            @endforeach
                            <td>{{ $fmt($total) }}</td>
                        @endforeach
                        @if (empty($withYears))
                            <td>&mdash;</td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="meta">
            Generated {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>
</body>

</html>
