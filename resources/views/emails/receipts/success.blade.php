@php
    $addressParts = array_filter([
        $order->address ?: $order->donor?->address,
        $order->city ?: $order->donor?->city,
        $order->state ?: $order->donor?->state,
        $order->pincode ?: $order->donor?->pincode,
        $order->country ?: $order->donor?->country,
    ], static fn ($value) => $value !== null && trim((string) $value) !== '');
    $fullAddress = ! empty($addressParts) ? implode(', ', $addressParts) : '';
    $receiptImages = $receiptImages ?? \App\Support\ReceiptAssets::all();
@endphp
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title> Donation Reciept</title>
  <link rel="shortcut icon" type="image/x-icon" href="{{ $receiptImages['favicon'] }}">

  <style>
    body{background: #eaefdc;}
    *{
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    .bdwrapper{
        padding: 60px 20px;
        background: #eaefdc;
    }

    .table
    {
        width: 800px;
        margin-left: auto;
        margin-right: auto;
        background-color: #fff;
        border: 2px solid #304C46;
        padding-top: 8px;
        font-size: 16px;
        border-collapse:collapse;
        font-family: DejaVu Sans, sans-serif;
    }
    .verticalalignTop td{
        vertical-align: text-top;
    }
    .FtG{
         font-family: DejaVu Sans, sans-serif;
    }
    td{
        font-size: 16px;
    }
  </style>
</head>
<body>
    <div class="bdwrapper">
        <table class="table">
            <tr>
                <td style="width: 50%; padding:8px 10px; font-size: 14px;">
                    <span>Reg. No. : E-9897 - RAJKOT</span>
                    <br>   
                    <span>PAN NO.  : AADTM7770L</span>
                    
                </td>
                <td style="text-align: end; width: 50%; padding:8px 10px; font-size: 14px;">
                   <span>URN No.  : AADTM7770LF20216</span>
                </td>
            </tr>
            <tr>
                <td style="width:50%; text-align: start; border-top:1px solid #d3d0d0; border-bottom:1px solid #d3d0d0;">
                    
                    <table style="width: 100%;border-collapse: collapse;">
                        <tbody>
                            <tr>
                                    <td class="FtG" style="padding: 8px 68px 0 10px; text-align: end; font-size: 14px;">માનવ સેવા ચેરીટેબલ ટ્રસ્ટ સંચાલિત</td></tr>
                            <tr>
                                <td style="padding: 0px 10px 8px 10px; text-align: center;"><img style="margin-top: -14px;" src="{{ $receiptImages['logo'] }}" style="max-width: 300px;height: auto;"></td>
                            </tr>
                        </tbody>
                    </table>
                    
                </td>
                <td class="FtG" style="width:50%; font-size: 16px; text-align: center; padding:8px 10px;border-top:1px solid #d3d0d0; border-bottom:1px solid #d3d0d0;border-left:1px solid #e7e7e7" colspan="2">વિનુભાઈ બચુભાઈ નાગ્રેચા પરિસર-સદભાવના વૃધ્ધાશ્રમ, <br>જામનગર-રાજકોટ હાઈવે, <br>મોટા રામપર, રાજકોટ મો. 85301 38001</td>
            </tr>
            <tr>
                <td style="width: 50%; padding:14px 10px;border-bottom:1px solid #d3d0d0;">
                    <span class="FtG" style="font-weight: 700;">નંબર : </span>
                     {{ $order->receiptNumberFormatted() }}
                </td>
                <td style="width: 50%;text-align:end; padding:14px 10px;border-bottom:1px solid #d3d0d0;">
                    <span class="FtG" style="font-weight: 700;">તારીખ : </span>
                    {{ \Carbon\Carbon::parse($order->paid_at ?? $order->created_at ?? now())->timezone(config('app.timezone', 'Asia/Kolkata'))->format('d/m/Y') }}
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-left:10px;padding-right:10px; width: 100%; padding-top: 8px;">
                    <table class="verticalalignTop FtG" style="width: 100%;">
                        <tr>
                            <td>ધર્માનુરાગીશ્રી&nbsp;:&nbsp;</td>
                            <td style="width:100%; border-bottom: 2px dotted #304C46;">&nbsp;&nbsp;{{ $order->donor_name ?? '' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-left:10px;padding-right:10px; width: 100%; padding-top: 4px;">
                    <table class="verticalalignTop" style="width: 100%;">
                        <tr>
                            <td style="width:100%; text-align: left; line-height: 1.5; word-break: break-word; overflow-wrap: anywhere;"><span class="FtG" style="font-size: 16px;">સરનામું&nbsp;:&nbsp;</span><span style="font-size: 16px; border-bottom: 2px dotted #304C46; padding-bottom: 1px;">&nbsp;&nbsp;
                               {{ $fullAddress !== '' ? $fullAddress : '—' }}
                            </span></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding-left:10px;padding-right:10px; padding-top: 4px;">
                    <table class="verticalalignTop" style="width: 100%;">
                        <tr>
                            <td style="white-space: nowrap;" class="FtG">મો.નં.  :</td>
                            <td style="width:100%; border-bottom: 2px dotted #304C46;">&nbsp;&nbsp;{{ $order->donor_phone ?? '' }}</td>
                        </tr>
                    </table>
                </td>
                <td style="padding-left:10px;padding-right:10px; padding-top: 4px;">
                    <table class="verticalalignTop" style="width: 100%;">
                        <tr>
                            <td style="white-space: nowrap;">PAN No. : </td>
                            <td style="width:100%; border-bottom: 2px dotted #304C46;"> &nbsp;&nbsp;{{ $order->pan_number ?? '' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
             <tr>
                <td colspan="2" style="padding-left:10px;padding-right:10px; width: 100%; padding-top: 4px;">
                    <table class="verticalalignTop" style="width: 100%;">
                        <tr>
                            <td style="white-space: nowrap;" class="FtG">આજ રોજ આપના તરફથી અંકે રૂ </td>
                            <td style="width:100%; border-bottom: 2px dotted #304C46;">&nbsp;&nbsp; {{ $amountInWords }} /-</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="padding-left:10px;padding-right:10px; width: 100%; padding-top: 4px;">
                    <table class="verticalalignTop" style="width: 100%;">
                        <tr>
                            <td style="width:100%; border-bottom: 2px dotted #304C46;"></td>
                            <td style="white-space: nowrap;" class="FtG"> મળેલ છે. સહકાર બાદલ આભાર...</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding-top:18px;padding-bottom:18px;padding-left:10px;padding-right:10px;">
                    <table style="width:300px;border:1px solid #304C46;border-collapse:collapse;">
                        <tr>
                            <td style="padding:5px 12px; width: 50px; background:#216B46;color: #fff; font-size: 26px;">₹</td>
                            <td style="padding: 6px 12px; font-size: 22px; width: 100%;text-align: center;">{{ $order->total_amount  ?? '' }} /-</td>
                        </tr>
                        <tr>
                            <td colspan="2"class="FtG" style="font-size: 12px;background: #216b46;color: #fff; text-align: center;">તમોએ આપેલું દાન ઇન્કમટૅક્સ ની કલમ ૮૦ જી (૫) નીચે કરમુક્ત છે </td>
                        </tr>
                    </table>
                </td>
                <td style="padding-top:18px;padding-bottom:18px;">
                     <table style="width:240px;border-collapse:collapse; margin-left: auto; text-align: center;">
                        <tr>
                            <td style="color:#8aa73c;font-weight: 600;" class="FtG">નાણા સ્વીકારનારની સહી</td>
                        </tr>
                        <tr>
                            <td><img src="{{asset('images/signature.jpeg')}}" style="max-width: 100%; height: 80px;"></td>
                        </tr>
                        <tr>
                            <td style="color:#216b46;font-weight: 600;" class="FtG">માનવ સેવા ચેરીટેબલ ટ્રસ્ટ સંચાલિત</td></tr>
                     </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="FtG" style="background:#304C46; color: #fff; font-size: 11.8px; padding:8px 10px;padding-right:1px;text-align: center;">આપના તરફથી મળેલ દાન બદલ અમો આપનો હૃદય પૂર્વક આભાર માનીએ છીએ. સાથો સાથ પરમ કૃપાળુ પરમાત્મા આપનો તથા આપણા પરિવારને સુ:ખ શાંતિ સમૃધ્ધિ આર્પે તેવી પ્રાર્થના...</td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 0;">
                    <table style="width: 100%;border-collapse:collapse;">
                        <tbody>
                            <tr>
                                <td style="width:20%; padding: 0;"><img src="{{ $receiptImages['photo1'] }}" style="width: 100%; height: 200px; object-fit: cover;"></td>
                                <td style="width:20%; padding: 0;"><img src="{{ $receiptImages['photo2'] }}" style="width: 100%; height: 200px; object-fit: cover;"></td>
                                <td style="width:20%; padding: 0;"><img src="{{ $receiptImages['photo3'] }}" style="width: 100%; height: 200px; object-fit: cover;"></td>
                                <td style="width:20%; padding: 0;"><img src="{{ $receiptImages['photo4'] }}" style="width: 100%; height: 200px; object-fit: cover;"></td>
                                <td style="width:20%; padding: 0;"><img src="{{ $receiptImages['photo5'] }}" style="width: 100%; height: 200px; object-fit: cover;"></td>
                            </tr>
                            <tr>
                                <td class="FtG" style="background:#304C46; color: #fff; text-align: center;">સદ્ભાવના વૃધ્ધાશ્રમ</td>
                                <td class="FtG" style="background:#304C46; color: #fff; text-align: center;">અન્નક્ષેત્ર સેવા </td>
                                <td class="FtG" style="background:#304C46; color: #fff; text-align: center;">બળદ આશ્રમ </td>
                                <td class="FtG" style="background:#304C46; color: #fff; text-align: center;">શ્વાન આશ્રમ </td>
                                <td class="FtG" style="background:#304C46; color: #fff; text-align: center;">પક્ષીઓની સેવા</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
