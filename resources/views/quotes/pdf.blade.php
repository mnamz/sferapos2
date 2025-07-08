@php $currency = config('app.currency', 'USD'); @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote #{{ $quote->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }
        .container {
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .shop-info {
            margin-bottom: 30px;
        }
        .order-info {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        .totals {
            float: right;
            width: 300px;
        }
        .totals table {
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="position: relative; min-height: 90px; margin-bottom: 10px;">
            <div style="width: 60%;">
                <span style="font-weight: bold;">Bill From:</span><br>
                <span>{{ $settings->shop_name }}</span><br>
                @if($settings->company_number)
                    <span>{{ $settings->company_number }}</span><br>
                @endif
                <span>{{ $settings->shop_address }}</span><br>
                @if($settings->shop_phone)
                    <span style="font-weight: bold;">Tel:</span> <span>{{ $settings->shop_phone }}</span><br>
                @endif
                @if($settings->shop_email)
                    <span style="font-weight: bold;">Email:</span> <span>{{ $settings->shop_email }}</span><br>
                @endif
            </div>
            @if($settings->invoice_logo_path)
                <img src="{{ public_path('storage/'.$settings->invoice_logo_path) }}" alt="Logo" style="position: absolute; top: 0; right: 0; max-width: 320px; max-height: 80px;">
            @elseif($settings->logo_path)
                <img src="{{ public_path('storage/'.$settings->logo_path) }}" alt="Logo" style="position: absolute; top: 0; right: 0; max-width: 320px; max-height: 80px;">
            @endif
        </div>

        <hr style="margin: 20px 0;">

        <table style="width: 100%; margin-bottom: 10px; margin-top: 5px; border: none;">
            <tr>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <span style="font-weight: bold;">Attn:</span><br>
                    @if($quote->customer)
                        <span>{{ $quote->customer->name }}</span><br>
                        @if($quote->customer->phone)
                            <span style="font-weight: bold;">Tel:</span> <span>{{ $quote->customer->phone }}</span><br>
                        @endif
                        @if($quote->customer->email)
                            <span style="font-weight: bold;">Email:</span> <span>{{ $quote->customer->email }}</span><br>
                        @endif
                        @if($quote->customer->address)
                            <span style="font-weight: bold;">Address:</span> <span>{{ $quote->customer->address }}</span><br>
                        @endif
                    @else
                        Walk-in Customer
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <table style="width: 100%; border: none; font-size: 14px;">
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Quote</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $quote->id }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Quote Date</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ \Carbon\Carbon::parse($quote->created_at)->format($settings->date_format ?? 'Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Issued By</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $quote->user->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Status</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ ucfirst($quote->status) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
            <thead>
                <tr style="background: #ddd;">
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Item</th>
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Quantity</th>
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Unit Price ({{ $settings->currency ?? 'RM' }})</th>
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Total ({{ $settings->currency ?? 'RM' }})</th>
                    <th style="padding: 8px 4px; background: #ddd;border: none; font-weight: bold; text-align: center;">Remark</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 6px 4px; border: none; text-align: left;">{{ $item->product_name }}</td>
                    <td style="padding: 6px 4px; border: none; text-align: center;">{{ $item->quantity }}</td>
                    <td style="padding: 6px 4px; border: none; text-align: center;">{{ number_format($item->price, 2) }}</td>
                    <td style="padding: 6px 4px; border: none; text-align: center;">{{ number_format($item->total, 2) }}</td>
                    <td style="padding: 6px 4px; border: none; text-align: left;">{{ $item->remark }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table style="width: 100%; margin-top: 5px; margin-bottom: 10px; border: none;">
            <tr>
                <td style="width: 50%; vertical-align: top; border: none;">
                    @if(!empty($quote->remarks))
                        <span style="font-weight: bold;">Remark</span><br>
                        {{ $quote->remarks }}
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <table style="width: 100%; border: none; font-size: 14px;">
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Subtotal</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $settings->currency ?? 'RM' }} {{ number_format($quote->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Tax</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $settings->currency ?? 'RM' }} {{ number_format($quote->tax, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; padding: 1px 0;">Discount</td>
                            <td style="border: none; text-align: right; padding: 1px 0;">{{ $settings->currency ?? 'RM' }} {{ number_format($quote->discount ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; font-weight: bold; background: #ddd; padding: 1px 0;">Total</td>
                            <td style="border: none; background: #ddd; text-align: right; font-weight: bold; padding: 1px 0;">{{ $settings->currency ?? 'RM' }} {{ number_format($quote->total, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html> 