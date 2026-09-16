<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        html, body {
            margin: 0;
            padding: 0;
            width: {{ $pageWidthPt }}pt;
            height: {{ $pageHeightPt }}pt;
        }
        * {
            font-family: "{{ $nameFontFamily }}", "Noto Sans Gujarati", "Noto Sans Devanagari", DejaVu Sans, sans-serif;
            box-sizing: border-box;
        }
        .page {
            position: relative;
            width: {{ $pageWidthPt }}pt;
            height: {{ $pageHeightPt }}pt;
            overflow: hidden;
        }
        .template-image {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ $pageWidthPt }}pt;
            height: {{ $pageHeightPt }}pt;
        }
        .donor-name-wrap {
            position: absolute;
            left: 0;
            width: 100%;
            top: {{ $nameTopPercent }}%;
            border-collapse: collapse;
        }
        .donor-name-cell {
            text-align: center;
            vertical-align: middle;
            padding: 0 12pt;
            margin: 0;
            font-family: "{{ $nameFontFamily }}", "Noto Sans Gujarati", "Noto Sans Devanagari", DejaVu Sans, sans-serif;
            font-size: {{ $nameSizePt }}pt;
            font-weight: bold;
            color: {{ $nameColor }} !important;
            line-height: 1.2;
            white-space: nowrap;
            letter-spacing: 0.01em;
            background: transparent;
        }
        .donor-name-cell--wrap {
            white-space: normal;
            max-width: {{ $nameMaxWidthPt }}pt;
            word-wrap: break-word;
            line-height: 1.2;
        }
        .donor-name {
            display: inline-block;
            background: transparent;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="page">
        <img class="template-image" src="{{ $templateImage }}" alt="">
        <table class="donor-name-wrap">
            <tr>
                <td class="donor-name-cell {{ $nameWrap ? 'donor-name-cell--wrap' : '' }}">
                    <span class="donor-name">{{ $donorName }}</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
