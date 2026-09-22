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
            font-family: {{ $nameFontFamily ?? '"noto sans gujarati", "Noto Sans Gujarati", DejaVu Sans, sans-serif' }};
            box-sizing: border-box;
        }

        .page {
            position: relative;
            width: {{ $pageWidthPt }}pt;
            height: {{ $pageHeightPt }}pt;
            overflow: hidden;
            background: #ffffff;
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
            border-collapse: collapse;
        }

        .donor-name-wrap {
            top: {{ $nameTopPercent }}%;
            left: 0;
            width: 100%;
        }

        .date-wrap {
            bottom: {{ $dateBottomPt }}pt;
            left: {{ $dateLeftPercent }}%;
            width: {{ $dateWidthPercent }}%;
            height: {{ $dateBoxHeightPt }}pt;
        }

        .donor-name-cell,
        .date-cell {
            vertical-align: middle;
            padding: 0;
            margin: 0;
        }

        .donor-name-cell {
            text-align: center;
            font-size: {{ $nameSizePt }}pt;
            font-weight: {{ $nameFontWeight ?? 'normal' }};
            font-family: {{ $nameFontFamily ?? '"noto sans gujarati", "Noto Sans Gujarati", DejaVu Sans, sans-serif' }};
            color: {{ $nameColor }} !important;
            line-height: 1.05;
            white-space: nowrap;
        }

        .donor-name-cell--wrap {
            white-space: normal;
            max-width: {{ $nameMaxWidthPt }}pt;
            word-wrap: break-word;
            line-height: 1.12;
        }

        .date-cell {
            height: {{ $dateBoxHeightPt }}pt;
            text-align: {{ $dateAlign }};
            font-size: {{ $dateSizePt }}pt;
            font-weight: normal;
            font-family: "noto sans gujarati", "Noto Sans Gujarati", sans-serif;
            color: {{ $dateColor }} !important;
            line-height: 1;
            white-space: nowrap;
            padding-left: {{ $datePaddingLeftPt }}pt;
            padding-right: {{ $datePaddingRightPt }}pt;
        }
    </style>
</head>
<body>
    <div class="page">
        @if (! empty($templateImage))
            <img class="template-image" src="{{ $templateImage }}" alt="">
        @endif
        <table class="donor-name-wrap">
            <tr>
                <td class="donor-name-cell{{ ($nameWrap ?? false) ? ' donor-name-cell--wrap' : '' }}" style="color: {{ $nameColor }}; font-family: {{ $nameFontFamily ?? 'DejaVu Sans, sans-serif' }};">
                    <span style="color: {{ $nameColor }}; font-family: {{ $nameFontFamily ?? 'DejaVu Sans, sans-serif' }};">{{ $donorName }}</span>
                </td>
            </tr>
        </table>
        </div>
</body>
</html>
