<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="UTF-8">
    <title>{{ $report['title'] ?? 'Donation Report' }}</title>
    <style>
        body {
            font-family: "noto sans gujarati", "Noto Sans Gujarati", DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        h1 {
            font-family: "noto sans gujarati", "Noto Sans Gujarati", DejaVu Sans, sans-serif;
            font-size: 18px;
            margin: 0 0 4px;
        }

        .meta {
            margin-bottom: 16px;
            color: #4b5563;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            font-family: "noto sans gujarati", "Noto Sans Gujarati", DejaVu Sans, sans-serif;
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
        }

        td.number, th.number {
            text-align: right;
        }

        tr.total td {
            font-weight: 700;
            background: #f9fafb;
        }
    </style>
</head>
<body>
    <h1>{{ $report['title'] ?? 'Donation Report' }}</h1>
    <div class="meta">
        <div><strong>Period:</strong> {{ $report['periodLabel'] ?? '—' }}</div>
        <div><strong>Generated:</strong> {{ $report['generatedAt'] ?? now()->format('d M Y, h:i A') }}</div>
        @if (! empty($report['grandTotal']))
            <div><strong>Grand total:</strong> ₹ {{ number_format((float) $report['grandTotal'], 2) }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                @foreach (($rows[0] ?? []) as $index => $header)
                    <th class="{{ $index > 0 ? 'number' : '' }}">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach (array_slice($rows, 1) as $row)
                <tr @class(['total' => ($row[0] ?? '') === 'Total'])>
                    @foreach ($row as $index => $cell)
                        <td class="{{ $index > 0 && is_numeric($cell) ? 'number' : '' }}">
                            @if (is_numeric($cell) && $index > 0)
                                {{ number_format((float) $cell, 2) }}
                            @else
                                {{ $cell }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
