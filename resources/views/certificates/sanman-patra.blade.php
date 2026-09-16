<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: {{ $pageWidthPt }}pt;
            height: {{ $pageHeightPt }}pt;
        }

        * {
            font-family: "noto sans gujarati", "Noto Sans Gujarati", DejaVu Sans, sans-serif;
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

        .donor-name-wrap,
        .date-wrap {
            position: absolute;
            left: 0;
            width: 100%;
            border-collapse: collapse;
        }

        .donor-name-wrap {
            top: {{ $nameTopPercent }}%;
        }

        .date-wrap {
            bottom: {{ $dateBottomPt }}pt;
            height: {{ $dateBoxHeightPt }}pt;
        }

        .donor-name-cell,
        .date-cell {
            text-align: center;
            vertical-align: middle;
            padding: 0;
            margin: 0;
        }

        .donor-name-cell {
            font-size: {{ $nameSizePt }}pt;
            font-weight: {{ $nameFontWeight ?? 'normal' }};
            color: {{ $nameColor }} !important;
            line-height: 1.2;
            white-space: nowrap;
        }

        .donor-name-cell--wrap {
            white-space: normal;
            max-width: {{ $nameMaxWidthPt }}pt;
            word-wrap: break-word;
            line-height: 1.15;
        }

        .date-cell {
            height: {{ $dateBoxHeightPt }}pt;
            font-size: {{ $dateSizePt }}pt;
            font-weight: normal;
            font-family: "noto sans gujarati", "Noto Sans Gujarati", sans-serif;
            color: {{ $dateColor }} !important;
            line-height: 1;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="page">
        <img class="template-image" src="{{ $templateImage }}" alt="">
        <table class="donor-name-wrap">
            <tr>
                <td class="donor-name-cell{{ ($nameWrap ?? false) ? ' donor-name-cell--wrap' : '' }}" style="color: {{ $nameColor }};">
                    <span style="color: {{ $nameColor }};">{{ $donorName }}</span>
                </td>
            </tr>
        </table>
        <table class="date-wrap">
            <tr>
                <td class="date-cell" style="color: {{ $dateColor }};">
                    <span style="color: {{ $dateColor }};">{{ $dateLine ?? ('તારીખ : '.$dateValue) }}</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
