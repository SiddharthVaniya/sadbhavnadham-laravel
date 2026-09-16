<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @if (!empty($pdfFontBase64))
        @font-face {
            font-family: "Noto Sans Gujarati";
            font-style: normal;
            font-weight: 400;
            src: url('data:application/font-truetype;base64,{{ $pdfFontBase64 }}') format('truetype');
        }
        @endif

        body {
            font-family: "Noto Sans Gujarati", sans-serif;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <h1>Donation Receipt</h1>
    <p>દાન રસીદ</p>
</body>
</html>
