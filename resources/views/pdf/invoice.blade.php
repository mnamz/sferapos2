@php
    $host = parse_url(config('app.url'), PHP_URL_HOST);
    $isOps = $host == 'ops.sfera.my';
    $cur = $settings->currency;
    $money = fn ($v) => $cur . ' ' . number_format((float) $v, 2);
    $invoiceNo = $isOps ? $order->created_at->format('dmY') . '-' . $order->id . '-INV' : $order->id;
    $logo = $settings->invoice_logo_path ?: $settings->logo_path;
    $isPaid = (float) $order->due_amount <= 0;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNo }}</title>
    <style>
        @page { margin: 36px 40px 70px 40px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #222; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .muted { color: #6b6b6b; }
        .label { font-size: 9.5px; letter-spacing: 1px; text-transform: uppercase; color: #6b6b6b; font-weight: bold; }
        .title { font-size: 30px; font-weight: bold; letter-spacing: 3px; color: #111; line-height: 1; }
        .rule { border-top: 2px solid #111; margin: 18px 0 20px; }
        .meta td { padding: 2px 0; }
        .meta td.k { color: #6b6b6b; }
        .meta td.v { text-align: right; font-weight: bold; }
        .items th { background: #111; color: #fff; font-size: 10px; letter-spacing: .8px; text-transform: uppercase; padding: 8px 10px; text-align: left; }
        .items td { padding: 9px 10px; border-bottom: 1px solid #e3e3e3; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .ctr { text-align: center; }
        .items tr.alt td { background: #f7f7f7; }
        .totals td { padding: 5px 10px; }
        .totals td.v { text-align: right; white-space: nowrap; }
        .totals tr.grand td { background: #111; color: #fff; font-weight: bold; font-size: 14px; padding: 9px 10px; }
        .totals tr.due td { font-weight: bold; border-top: 1px solid #e3e3e3; }
        .box { border: 1px solid #e3e3e3; border-left: 3px solid #111; padding: 10px 12px; }
        .stamp { display: inline-block; border: 2px solid #1a7f37; color: #1a7f37; font-weight: bold; letter-spacing: 2px; padding: 3px 10px; font-size: 12px; }
        .footer { position: fixed; bottom: -45px; left: 0; right: 0; text-align: center; font-size: 9.5px; color: #8a8a8a; border-top: 1px solid #e3e3e3; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="footer">
        Thank you for your business. &middot; {{ $settings->shop_name }}
        @if($settings->shop_email) &middot; {{ $settings->shop_email }} @endif
        @if($settings->shop_phone) &middot; {{ $settings->shop_phone }} @endif
        <br>This is a computer-generated invoice. No signature is required.
    </div>

    {{-- Header: brand left, document title right --}}
    <table>
        <tr>
            <td style="width: 60%;">
                @if($logo)
                    <img src="{{ public_path('storage/'.$logo) }}" alt="Logo" style="max-height: 56px; max-width: 220px; margin-bottom: 8px;"><br>
                @endif
                <div style="font-size: 15px; font-weight: bold; color: #111;">{{ $settings->shop_name }}</div>
                <div class="muted" style="font-size: 11px;">
                    @if($settings->company_number){{ $settings->company_number }}<br>@endif
                    {{ $settings->shop_address }}<br>
                    @if($settings->shop_phone){{ $settings->shop_phone }}@endif
                    @if($settings->shop_phone && $settings->shop_email) &nbsp;&middot;&nbsp; @endif
                    @if($settings->shop_email){{ $settings->shop_email }}@endif
                </div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="title">INVOICE</div>
                <div style="margin-top: 8px; font-size: 13px; font-weight: bold;"># {{ $invoiceNo }}</div>
                @if($isPaid)
                    <div style="margin-top: 10px;"><span class="stamp">PAID</span></div>
                @endif
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- Bill to + invoice meta --}}
    <table>
        <tr>
            <td style="width: 55%; padding-right: 20px;">
                <div class="label">Bill To</div>
                @if($order->customer)
                    <div style="font-size: 13px; font-weight: bold; color: #111; margin-top: 3px;">{{ $order->customer->name }}</div>
                    @if($order->customer->address)<div>{{ $order->customer->address }}</div>@endif
                    @if($order->customer->phone)<div class="muted">Tel: {{ $order->customer->phone }}</div>@endif
                    @if($order->customer->email)<div class="muted">{{ $order->customer->email }}</div>@endif
                @else
                    <div class="muted">Walk-in customer</div>
                @endif
            </td>
            <td style="width: 45%;">
                <table class="meta">
                    <tr><td class="k">Invoice No.</td><td class="v">{{ $invoiceNo }}</td></tr>
                    <tr><td class="k">Invoice Date</td><td class="v">{{ $order->created_at->format($settings->date_format) }}</td></tr>
                    <tr><td class="k">{{ $isOps ? 'Issued By' : 'Salesman' }}</td><td class="v">{{ $order->user->name ?? '-' }}</td></tr>
                    <tr><td class="k">Payment Method</td><td class="v">{{ ucwords(str_replace(['_', '-'], ' ', $order->payment_method)) }}</td></tr>
                    @if(!$isOps)
                        <tr><td class="k">Delivery</td><td class="v">{{ $order->delivery_method ?? '-' }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Line items --}}
    <table class="items" style="margin-top: 24px;">
        <thead>
            <tr>
                <th style="width: 6%;" class="ctr">#</th>
                <th style="width: 50%;">Description</th>
                <th style="width: 10%; text-align: center;">Qty</th>
                <th style="width: 17%; text-align: right;">Unit Price</th>
                <th style="width: 17%; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
                <tr class="{{ $i % 2 ? 'alt' : '' }}">
                    <td class="ctr muted">{{ $i + 1 }}</td>
                    <td>
                        <span style="font-weight: bold; color: #111;">{{ $item->product_name }}</span>
                        @if($item->remark)<br><span class="muted" style="font-size: 11px;">{{ $item->remark }}</span>@endif
                    </td>
                    <td class="ctr">{{ $item->quantity }}</td>
                    @if((float) $item->total == 0)
                        <td class="num muted">&ndash;</td>
                        <td class="num muted">Included</td>
                    @else
                        <td class="num">{{ number_format($item->price, 2) }}</td>
                        <td class="num">{{ number_format($item->total, 2) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Notes / payment details + totals --}}
    <table style="margin-top: 18px;">
        <tr>
            <td style="width: 55%; padding-right: 24px;">
                @if($settings->footer_text)
                    <div class="box">
                        <div class="label" style="margin-bottom: 4px;">Payment Details</div>
                        {!! nl2br(e($settings->footer_text)) !!}
                    </div>
                @endif
                @if(!empty($order->remarks))
                    <div style="margin-top: 14px;">
                        <div class="label">Remarks</div>
                        {{ $order->remarks }}
                    </div>
                @endif
            </td>
            <td style="width: 45%;">
                <table class="totals">
                    <tr><td class="muted">Subtotal</td><td class="v">{{ $money($order->subtotal) }}</td></tr>
                    @if((float) $order->discount > 0)
                        <tr><td class="muted">Discount</td><td class="v">- {{ $money($order->discount) }}</td></tr>
                    @endif
                    @if((float) $order->tax > 0)
                        <tr><td class="muted">Tax</td><td class="v">{{ $money($order->tax) }}</td></tr>
                    @endif
                    @if(!$isOps && (float) $order->delivery_cost > 0)
                        <tr><td class="muted">Delivery</td><td class="v">{{ $money($order->delivery_cost) }}</td></tr>
                    @endif
                    <tr class="grand"><td>Total</td><td class="v">{{ $money($order->total) }}</td></tr>
                    <tr class="due"><td>Balance Due</td><td class="v">{{ $money($order->due_amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
