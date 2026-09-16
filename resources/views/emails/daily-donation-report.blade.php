<x-mail::message>
# Daily donations report

Please find attached the Excel report for **{{ $formalBritishDate }}**.

- Paid orders included: **{{ $orderCount }}**
- Last column **Check**: mark rows with ☐ / ☑ in Excel

Thanks,<br>
{{ config('branding.name', config('app.name')) }}
</x-mail::message>
