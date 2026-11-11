<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milk Production Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #333;
            font-size: 11px;
            line-height: 1.4;
        }

        .container {
            padding: 30px;
            max-width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 24px;
            color: #0066cc;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 12px;
            color: #666;
        }

        .subtitle {
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .report-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
            font-size: 10px;
        }

        .report-info-item {
            display: inline-block;
            margin-right: 30px;
        }

        .report-info-label {
            font-weight: 600;
            color: #0066cc;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            padding: 12px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }

        .stat-value {
            font-size: 16px;
            font-weight: 700;
            color: #0066cc;
            margin-bottom: 3px;
        }

        .stat-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-wrapper {
            margin-bottom: 30px;
        }

        .table-title {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #0066cc;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        thead {
            color: #333;
        }

        th {
            padding: 8px 6px;
            text-align: right;
            font-weight: 600;
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        th:first-child {
            text-align: left;
        }

        td {
            padding: 6px 6px;
            text-align: right;
            border: 1px solid #ddd;
        }

        td:first-child {
            text-align: left;
            font-weight: 500;
            background-color: #f9f9f9;
        }

        tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        tbody tr:hover {
            background-color: #f0f5ff;
        }

        .text-right {
            text-align: right;
        }

        .number {
            font-variant-numeric: tabular-nums;
            text-align: right;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9px;
            color: #999;
        }

        @page {
            margin: 20px;
            size: A4 landscape;
        }

        .page-break {
            page-break-after: always;
            margin-bottom: 20px;
        }

        .charts-page {
            page-break-before: always;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            background-color: #f5f5f5;
            border: 1px dashed #ccc;
            border-radius: 4px;
            color: #999;
            font-size: 12px;
        }

        .percent-cell {
            color: #0066cc;
            font-weight: 500;
        }

        .month-label {
            font-weight: 600;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Milk Production Dashboard</h1>
            <p>Philippine Carabao Center (PCC)</p>
        </div>

        <!-- Report Information -->
        <div class="report-info">
            <div class="report-info-item">
                <span class="report-info-label">Report Type:</span>
                {{ ucfirst($reportType) }}
            </div>
        </div>

        <!-- Main Dashboard Table -->
        <div class="table-wrapper">
            <div class="table-title">
                Monthly Performance Scorecard - {{ $dashboard['month_label'] ?? '' }} {{ $dashboard['year'] ?? '' }}
            </div>

            @if (!empty($dashboard['rows']))
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: left;">Center</th>
                            <th>Cum. Monthly Target</th>
                            <th>Monthly Total</th>
                            @foreach ($dashboard['weeks'] ?? [] as $week)
                                <th>WK {{ $week }}</th>
                            @endforeach
                            <th>Monthly Rating (%)</th>
                            <th>Lead Rank</th>
                            <th>Cum. Accomplishment</th>
                            <th>Annual Target</th>
                            <th>Annual Accomplishment</th>
                            <th>% Accomplishment</th>
                        </tr>
                        <tr>
                            <th style="text-align: left;"></th>
                            <th></th>
                            <th></th>
                            @foreach ($dashboard['weeks'] ?? [] as $week)
                                <th style="font-size: 9px; font-weight: normal;">
                                    {{ $dashboard['week_ranges'][$week]['label'] ?? '' }}
                                </th>
                            @endforeach
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dashboard['rows'] as $row)
                            <tr>
                                <td style="text-align: left;">{{ $row['center_code'] ?? '—' }}</td>
                                <td class="number">{{ number_format($row['cumulative_monthly_target'] ?? 0, 2) }}</td>
                                <td class="number">{{ number_format($row['monthly_total'] ?? 0, 2) }}</td>
                                @foreach ($dashboard['weeks'] ?? [] as $week)
                                    <td class="number">
                                        {{ number_format($row['weeks'][$week] ?? 0, 2) }}
                                    </td>
                                @endforeach
                                <td class="number percent-cell">{{ number_format($row['monthly_rating'] ?? 0, 2) }}%
                                </td>
                                <td class="number">{{ $row['lead_rank'] ?? '—' }}</td>
                                <td class="number">
                                    {{ number_format($row['previous_month_cumulative_accomplishment'] ?? 0, 2) }}</td>
                                <td class="number">{{ number_format($row['annual_target'] ?? 0, 2) }}</td>
                                <td class="number">{{ number_format($row['annual_accomplishment'] ?? 0, 2) }}</td>
                                <td class="number percent-cell">{{ number_format($row['annual_percent'] ?? 0, 2) }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="no-data">
                    No dashboard data available for the selected period.
                </div>
            @endif
        </div>

        @if (!empty($dashboard['rows']))
            <div class="charts-page">
                {{-- Donut: centered as-is --}}
                <div style="text-align: center; margin-bottom: 30px;">
                    @php
                        $totalTarget = array_sum(array_column($dashboard['rows'], 'annual_target'));
                        $totalAccomplishment = array_sum(array_column($dashboard['rows'], 'annual_accomplishment'));
                        $totalBalance = $totalTarget - $totalAccomplishment;
                        $percentage = $totalTarget > 0 ? min(100, ($totalAccomplishment / $totalTarget) * 100) : 0;
                    @endphp

                    @php
                        $dcx = 160;
                        $dcy = 160;
                        $dr = 115;
                        $dcirc = 2 * M_PI * $dr;
                        $darcAcc = ($percentage / 100) * $dcirc;
                        $darcBal = $dcirc - $darcAcc;
                        $doffset = $dcirc / 4;
                    @endphp
                    <svg width="320" height="320" viewBox="0 0 320 320" xmlns="http://www.w3.org/2000/svg"
                        style="display:block; margin:0 auto; filter:drop-shadow(0 4px 12px rgba(0,0,0,0.12));">

                        <circle cx="{{ $dcx }}" cy="{{ $dcy }}" r="{{ $dr }}"
                            fill="none" stroke="#22C55E" stroke-width="40" />

                        <circle cx="{{ $dcx }}" cy="{{ $dcy }}" r="{{ $dr }}"
                            fill="none" stroke="#FBBF24" stroke-width="40"
                            stroke-dasharray="{{ $darcAcc }} {{ $darcBal }}"
                            stroke-dashoffset="{{ $doffset }}" stroke-linecap="butt" />

                        <circle cx="{{ $dcx }}" cy="{{ $dcy }}" r="94" fill="#2563eb" />

                        <text x="{{ $dcx }}" y="153" text-anchor="middle" font-family="Arial,sans-serif"
                            font-size="15" font-weight="600" fill="#FBBF24">{{ number_format($percentage, 2) }}%</text>

                        <text x="{{ $dcx }}" y="174" text-anchor="middle" font-family="Arial,sans-serif"
                            font-size="16" font-weight="bold"
                            fill="#ffffff">{{ number_format($totalAccomplishment, 2) }}</text>
                    </svg>

                    <!-- Legend -->
                    <div
                        style="display:flex; justify-content:center; gap:24px; margin-top:24px; font-size:11px; font-weight:500;">
                        <div style="display:flex; align-items:center; gap:4px;">
                            <span
                                style="display:inline-block; width:24px; height:16px; background:#FBBF24; border:1px solid #b45309;"></span>
                            <span>Accomp</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:4px;">
                            <span
                                style="display:inline-block; width:24px; height:16px; background:#22C55E; border:1px solid #15803d;"></span>
                            <span>Balance</span>
                        </div>
                    </div>
                </div>

                @php
                    $maxTarget = max(array_column($dashboard['rows'], 'annual_target')) ?: 1;
                    $barMaxHeight = 160;
                @endphp

                {{-- Bar chart: full width, aligned with table --}}
                <div style="width: 100%; margin-bottom: 40px;">
                    <div
                        style="margin-bottom: 10px; font-size: 14px; font-weight: 600; color: #0066cc; text-align: center;">
                        Center Performance Breakdown
                    </div>

                    <div
                        style="display: flex; align-items: flex-end; justify-content: center; gap: 6px; height: 192px; padding: 0 15px;">
                        @foreach ($dashboard['rows'] as $row)
                            @php
                                $balance = max(0, $row['annual_target'] - $row['annual_accomplishment']);
                                $accomp = $row['annual_accomplishment'];
                                $balanceHeight =
                                    $maxTarget > 0 ? max(4, round(($balance / $maxTarget) * $barMaxHeight)) : 4;
                                $accompHeight =
                                    $maxTarget > 0 ? max(4, round(($accomp / $maxTarget) * $barMaxHeight)) : 4;
                            @endphp
                            <div
                                style="display:flex; flex-direction:column; justify-content:flex-end; width:56px; height:192px; background:#f1f5f9;">
                                <div style="background:#22C55E; height:{{ $balanceHeight }}px;"></div>
                                <div style="background:#FBBF24; height:{{ $accompHeight }}px;"></div>
                            </div>
                        @endforeach
                    </div>

                    <div style="display: flex; justify-content: center; gap: 6px; padding: 0 15px; margin-top: 4px;">
                        @foreach ($dashboard['rows'] as $row)
                            <div style="width: 56px; text-align: center;">
                                <div
                                    style="background: #1e293b; color: white; font-size: 8px; padding: 1px; margin-bottom: 2px; font-weight: 500;">
                                    {{ number_format($row['annual_target'] - $row['annual_accomplishment'], 0) }}
                                </div>
                                <div
                                    style="background: #ca8a04; color: white; font-size: 8px; padding: 1px; margin-bottom: 2px; font-weight: 500;">
                                    {{ number_format($row['annual_accomplishment'], 0) }}
                                </div>
                                <div style="font-size: 8px; font-weight: bold; margin-top: 2px; color: #0f172a;">
                                    {{ $row['center_code'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        @endif

    </div>
</body>

</html>
