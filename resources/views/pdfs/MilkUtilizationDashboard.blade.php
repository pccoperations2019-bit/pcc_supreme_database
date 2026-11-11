<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milk Utilization</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #000;
            font-size: 12px;
            line-height: 1.5;
            background: #fff;
        }

        .page {
            background: #fff;
            border-radius: 8px;
            padding: 32px 24px;
        }

        .table-card {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            font-size: 11px;
        }

        thead th {
            background: #f8fafc;
            color: #334155;
            font-weight: 600;
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
            white-space: nowrap;
        }

        tbody td {
            padding: 6px;
            border-bottom: 1px solid #e2e8f0;
            text-align: right;
            color: #334155;
            font-variant-numeric: tabular-nums;
        }

        tbody td:first-child {
            text-align: left;
            font-weight: 500;
            color: #0f172a;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .title-row {
            font-weight: 600;
            color: #0f172a;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .meta {
            margin-top: 24px;
            font-size: 10px;
            color: #64748b;
        }

        @page {
            size: A4 landscape;
            margin: 12mm;
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="table-card">
            <table>
                @foreach ($rows as $row)
                    @if ($loop->first)
                        <thead>
                            <tr>
                                @foreach ($row as $cell)
                                    <th>{{ $cell }}</th>
                                @endforeach
                            </tr>
                        </thead>
                    @elseif (empty($row) || (count($row) === 1 && empty($row[0])))
                        {{-- Skip empty rows --}}
                    @else
                        <tbody>
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        </tbody>
                    @endif
                @endforeach
            </table>
        </div>

        <div class="meta">
            Generated {{ now()->format('Y-m-d H:i') }}
        </div>
    </div>
</body>

</html>
