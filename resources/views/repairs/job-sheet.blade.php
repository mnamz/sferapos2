@php
    $currency = $settings->currency ?? 'RM';
    $thermal = $format === 'thermal';
    $terms = collect(preg_split('/\r\n|\r|\n/', (string) ($settings->repair_terms ?? '')))->map(fn ($t) => trim($t))->filter();
    if ($terms->isEmpty()) {
        $terms = collect([
            'Please bring this job sheet when collecting your device.',
            'We are not responsible for data loss. Please back up your data before repair.',
            'Devices not collected within 30 days of notification may be disposed of to recover costs.',
            'Warranty covers the replaced part/workmanship only, and is void for physical or liquid damage.',
            'Deposits are non-refundable once parts have been ordered.',
        ]);
    }
    $checks = $checks ?? [];
    $pre = $job->pre_checks ?? [];
    $balance = max(0, $job->quotedTotal() - (float) $job->deposit);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Job Sheet {{ $job->job_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; background: #f3f4f6; }
        .page { background: #fff; margin: 16px auto; padding: {{ $thermal ? '8px' : '28px 32px' }}; width: {{ $thermal ? '80mm' : '210mm' }}; font-size: {{ $thermal ? '11px' : '12.5px' }}; }
        h1 { font-size: {{ $thermal ? '15px' : '20px' }}; margin: 0; }
        h2 { font-size: {{ $thermal ? '12px' : '13px' }}; margin: 14px 0 6px; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #111; padding-bottom: 2px; }
        .muted { color: #555; }
        .row { display: flex; gap: 16px; {{ $thermal ? 'flex-direction: column; gap: 4px;' : '' }} }
        .col { flex: 1; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 4px; vertical-align: top; text-align: left; }
        .kv td:first-child { width: 38%; color: #444; }
        .items th { border-bottom: 1px solid #111; }
        .items td { border-bottom: 1px dashed #bbb; }
        .num { text-align: right; }
        .jobno { font-size: {{ $thermal ? '18px' : '22px' }}; font-weight: bold; letter-spacing: .05em; }
        .box { border: 1px solid #111; padding: 6px 8px; }
        .checks { display: grid; grid-template-columns: repeat({{ $thermal ? 2 : 4 }}, 1fr); gap: 2px 10px; }
        .terms { font-size: {{ $thermal ? '9.5px' : '10.5px' }}; padding-left: 16px; margin: 4px 0; }
        .sign { display: flex; gap: 24px; margin-top: 28px; {{ $thermal ? 'flex-direction: column; gap: 22px;' : '' }} }
        .sign div { flex: 1; border-top: 1px solid #111; padding-top: 4px; text-align: center; }
        .cut { border-top: 2px dashed #888; margin: 22px 0 14px; position: relative; }
        .cut span { position: absolute; top: -9px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 8px; font-size: 10px; color: #666; }
        .toolbar { text-align: center; margin: 12px; }
        .toolbar a, .toolbar button { font: inherit; padding: 6px 12px; margin: 0 4px; border: 1px solid #999; background: #fff; border-radius: 4px; cursor: pointer; color: #111; text-decoration: none; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; width: auto; }
            @page { size: {{ $thermal ? '80mm auto' : 'A4' }}; margin: {{ $thermal ? '2mm' : '10mm' }}; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">Print</button>
    <a href="?format={{ $thermal ? 'a4' : 'thermal' }}">Switch to {{ $thermal ? 'A4' : '80mm thermal' }}</a>
    <a href="{{ route('repairs.show', $job) }}">Back to job</a>
</div>

<div class="page">
    <div class="row" style="align-items: flex-start;">
        <div class="col">
            <h1>{{ $settings->shop_name ?? config('app.name') }}</h1>
            <div class="muted">
                @if($settings?->company_number){{ $settings->company_number }}<br>@endif
                {{ $settings->shop_address ?? '' }}<br>
                @if($settings?->shop_phone)Tel: {{ $settings->shop_phone }}@endif
            </div>
        </div>
        <div class="col" style="{{ $thermal ? '' : 'text-align:right' }}">
            <div class="muted">REPAIR JOB SHEET</div>
            <div class="jobno">{{ $job->job_number }}</div>
            <div>Received: {{ $job->created_at->format('d M Y, h:i A') }}</div>
            @if($job->promised_at)<div>Est. ready: <b>{{ $job->promised_at->format('d M Y, h:i A') }}</b></div>@endif
            @if($job->priority === 'urgent')<div><b>** URGENT **</b></div>@endif
        </div>
    </div>

    <div class="row">
        <div class="col">
            <h2>Customer</h2>
            <table class="kv">
                <tr><td>Name</td><td><b>{{ $job->customer->name ?? '-' }}</b></td></tr>
                <tr><td>Phone</td><td>{{ $job->customer->phone ?? '-' }}</td></tr>
                @if($job->customer?->email)<tr><td>Email</td><td>{{ $job->customer->email }}</td></tr>@endif
            </table>
        </div>
        <div class="col">
            <h2>Device</h2>
            <table class="kv">
                <tr><td>Device</td><td><b>{{ $job->device_label }}</b> ({{ \App\Models\RepairJob::DEVICE_TYPES[$job->device_type] ?? $job->device_type }})</td></tr>
                <tr><td>IMEI / Serial</td><td>{{ $job->imei ?: '-' }}</td></tr>
                @if($job->color)<tr><td>Colour</td><td>{{ $job->color }}</td></tr>@endif
                <tr><td>Passcode</td><td>{{ $job->passcode_type === 'none' ? 'None' : ucfirst($job->passcode_type).' provided' }}</td></tr>
            </table>
        </div>
    </div>

    <h2>Reported Issue</h2>
    <div class="box">{!! nl2br(e($job->issue)) !!}</div>
    @if($job->diagnosis)
        <h2>Diagnosis</h2>
        <div>{!! nl2br(e($job->diagnosis)) !!}</div>
    @endif

    <h2>Condition at Intake</h2>
    @if(count($pre))
        <div class="checks">
            @foreach($checks as $key => $label)
                @if(isset($pre[$key]))
                    <div>{{ $pre[$key] === 'ok' ? '✔' : ($pre[$key] === 'faulty' ? '✘' : '–') }} {{ $label }}</div>
                @endif
            @endforeach
        </div>
    @endif
    @if($job->condition_notes)<div style="margin-top:4px;"><b>Notes:</b> {{ $job->condition_notes }}</div>@endif
    <div style="margin-top:4px;"><b>Accessories received:</b> {{ count($job->accessories ?? []) ? implode(', ', $job->accessories) : 'None' }}</div>

    <h2>Charges</h2>
    @if($job->items->isNotEmpty())
        <table class="items">
            <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">{{ $currency }}</th></tr></thead>
            <tbody>
            @foreach($job->items as $item)
                <tr>
                    <td>{{ $item->name }}@if($item->warranty_days) <span class="muted">({{ $item->warranty_days }}d warranty)</span>@endif</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format($item->price * $item->quantity, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    <table class="kv" style="margin-top:4px;">
        <tr><td>{{ $job->items->isNotEmpty() ? 'Total' : 'Estimated cost' }}</td><td class="num"><b>{{ $currency }} {{ number_format($job->quotedTotal(), 2) }}</b></td></tr>
        <tr><td>Deposit paid</td><td class="num">{{ $currency }} {{ number_format($job->deposit, 2) }}@if($job->deposit_method) ({{ str_replace('_', ' ', $job->deposit_method) }})@endif</td></tr>
        <tr><td>Balance</td><td class="num"><b>{{ $currency }} {{ number_format($balance, 2) }}</b></td></tr>
        <tr><td>Warranty</td><td class="num">{{ $job->warranty_days }} days from collection</td></tr>
    </table>

    <h2>Terms &amp; Conditions</h2>
    <ol class="terms">
        @foreach($terms as $term)<li>{{ $term }}</li>@endforeach
    </ol>
    <div class="muted" style="font-size: 10px;">Track your repair at {{ $trackUrl }}?job={{ $job->job_number }} with job number {{ $job->job_number }} and your phone number.</div>

    <div class="sign">
        <div>Customer signature</div>
        <div>Received by: {{ $job->receivedBy->name ?? '' }}</div>
    </div>

    {{-- Tear-off tag for the device --}}
    <div class="cut"><span>✂ device tag</span></div>
    <div class="row" style="align-items:center;">
        <div class="col"><span class="jobno">{{ $job->job_number }}</span></div>
        <div class="col">
            <b>{{ $job->customer->name ?? '-' }}</b> · {{ $job->customer->phone ?? '' }}<br>
            {{ $job->device_label }} · {{ $job->imei ?: 'no IMEI' }}<br>
            <span class="muted">{{ \Illuminate\Support\Str::limit($job->issue, 80) }}</span>
        </div>
    </div>
</div>
</body>
</html>
