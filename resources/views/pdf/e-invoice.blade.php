<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>E-Invoice #{{ $order['id'] }}</title>
    <style>
        @page {
            margin: 15mm;
            size: A4;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        .container {
            padding: 10px;
        }
        .banner {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #dc2626;
            color: white;
            text-align: center;
            padding: 4px 20px;
            font-size: 10px;
            font-weight: bold;
            transform: rotate(12deg);
            z-index: 10;
            font-family: Arial, sans-serif;
        }
        .relative {
            position: relative;
        }
        .header {
            margin-bottom: 12px;
            text-align: center;
        }
        .supplier-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .shop-address {
            font-size: 10px;
            color: #4b5563;
        }
        .cards-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .card {
            display: table-cell;
            vertical-align: top;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            font-size: 10px;
        }
        .card-1 {
            width: 50%;
            padding-right: 8px;
        }
        .card-2 {
            width: 50%;
            padding-left: 8px;
        }
        .cards-row .card {
            display: table-cell;
        }
        .card-3 {
            width: 100%;
            margin-bottom: 10px;
            display: block;
        }
        .card-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 6px;
            color: #111827;
        }
        .card-item {
            margin-bottom: 3px;
            font-size: 10px;
        }
        .card-label {
            font-weight: 500;
        }
        .divider {
            border-top: 1px solid #d1d5db;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10px;
        }
        th, td {
            padding: 5px 4px;
            border: 1px solid #333;
            text-align: left;
            font-size: 10px;
        }
        th {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-section {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .totals-spacer {
            display: table-cell;
            width: 60%;
        }
        .totals-box {
            display: table-cell;
            width: 40%;
            vertical-align: top;
        }
        .total-row {
            display: table;
            width: 100%;
            margin-bottom: 3px;
            font-size: 10px;
        }
        .total-label, .total-value {
            display: table-cell;
            font-size: 10px;
        }
        .total-value {
            text-align: right;
            padding-left: 10px;
        }
        .total-row-bold {
            font-weight: bold;
            font-size: 10px;
            padding-top: 4px;
            border-top: 1px solid #333;
        }
        .footer-section {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
            font-size: 10px;
        }
        .footer-left {
            font-size: 10px;
            margin-bottom: 10px;
        }
        .qr-code-section {
            margin-top: 20px;
            text-align: center;
            padding-top: 10px;
            border-top: 1px solid #d1d5db;
        }
        .qr-code {
            text-align: center;
            margin-bottom: 6px;
        }
        .qr-code img {
            width: 80px;
            height: 80px;
            border: 1px solid #333;
            display: block;
            margin: 0 auto 4px;
        }
        .qr-code-text {
            font-size: 10px;
            color: #6b7280;
        }
        .validation-date {
            font-size: 10px;
            color: #4b5563;
            margin-top: 4px;
        }
        .end-text {
            font-size: 10px;
            color: #6b7280;
            font-style: italic;
            margin-top: 4px;
        }
        .status-validated {
            color: #059669;
            font-weight: 600;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="relative">
            @if(!$order['myinvois_invoice'])
            <div class="banner">FOR ILLUSTRATION PURPOSES ONLY</div>
            @endif

            <!-- Header: Supplier Name and Address -->
            <div class="header">
                <div class="supplier-name">{{ $shopSettings['shop_name'] }}</div>
                <div class="shop-address">{{ $shopSettings['shop_address'] }}</div>
            </div>

            <!-- Card 1 and Card 2 Side by Side -->
            <div class="cards-row">
                <!-- Card 1: Supplier Details -->
                <div class="card card-1">
                    <div class="card-title">Supplier Details</div>
                    <div class="card-item"><span class="card-label">Name:</span> {{ $shopSettings['shop_name'] }}</div>
                    <div class="card-item"><span class="card-label">TIN:</span> {{ $shopSettings['tax_number'] ?? 'N/A' }}</div>
                    @if($shopSettings['identification_number'])
                    <div class="card-item"><span class="card-label">Reg. No:</span> {{ $shopSettings['identification_number'] }}</div>
                    @endif
                    @if($shopSettings['identification_scheme'])
                    <div class="card-item"><span class="card-label">ID Type:</span> {{ $shopSettings['identification_scheme'] }}</div>
                    @endif
                    <div class="card-item"><span class="card-label">Address:</span> {{ $shopSettings['shop_address'] }}</div>
                    <div class="card-item"><span class="card-label">Phone:</span> {{ $shopSettings['shop_phone'] }}</div>
                    @if($shopSettings['industry_classification_code'])
                    <div class="card-item"><span class="card-label">MSIC:</span> {{ $shopSettings['industry_classification_code'] }}</div>
                    @endif
                </div>

                <!-- Card 2: E-Invoice Details -->
                <div class="card card-2">
                    <div class="card-title">E-Invoice Details</div>
                    <div class="card-item"><span class="card-label">e-Invoice Code:</span> {{ $order['myinvois_invoice']['invoice_code_number'] ?? 'N/A' }}</div>
                    @if($order['myinvois_invoice'])
                    <div class="card-item"><span class="card-label">Unique ID:</span>{{ $order['myinvois_invoice']['uuid'] }}</div>
                    @endif
                    <div class="card-item"><span class="card-label">Invoice Date:</span> {{ \Carbon\Carbon::parse($order['created_at'])->format('Y-m-d H:i:s') }}</div>
                    @if($order['myinvois_invoice'])
                    <div class="card-item"><span class="card-label">Validated:</span> {{ \Carbon\Carbon::parse($order['myinvois_invoice']['created_at'])->format('Y-m-d H:i:s') }}</div>
                    <div class="card-item status-validated">Status: Validated</div>
                    @else
                    <div class="card-item">Status: Not Submitted</div>
                    @endif
                </div>
            </div>

            <!-- Horizontal Divider -->
            <div class="divider"></div>

            <!-- Card 3: Buyer Information -->
            <div class="card card-3">
                <div class="card-title">Buyer Information</div>
                <div style="display: table; width: 100%;">
                    <div style="display: table-cell; width: 50%; padding-right: 10px; vertical-align: top;">
                        <div class="card-item"><span class="card-label">Name:</span> {{ $order['customer']['name'] ?? 'Walk-in Customer' }}</div>
                        @if(isset($order['customer']['tin']))
                        <div class="card-item"><span class="card-label">TIN:</span> {{ $order['customer']['tin'] }}</div>
                        @endif
                        @if(isset($order['customer']['brn']) || isset($order['customer']['nric']))
                        <div class="card-item"><span class="card-label">Reg. No:</span> {{ $order['customer']['brn'] ?? $order['customer']['nric'] ?? 'N/A' }}</div>
                        @endif
                    </div>
                    <div style="display: table-cell; width: 50%; padding-left: 10px; vertical-align: top;">
                        @php
                            $buyerAddress = 'N/A';
                            if(isset($order['customer']['address'])) {
                                $parts = array_filter([
                                    $order['customer']['address'],
                                    $order['customer']['city'] ?? null,
                                    $order['customer']['postal_code'] ?? null,
                                    $order['customer']['state_code'] ?? null,
                                    $order['customer']['country'] ?? null
                                ]);
                                $buyerAddress = !empty($parts) ? implode(', ', $parts) : 'N/A';
                            }
                        @endphp
                        <div class="card-item"><span class="card-label">Address:</span> {{ $buyerAddress }}</div>
                        <div class="card-item"><span class="card-label">Phone:</span> {{ $order['customer']['phone'] ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">Class</th>
                        <th style="width: 30%;">Description</th>
                        <th style="width: 6%;" class="text-center">Qty</th>
                        <th style="width: 10%;" class="text-right">Unit Price</th>
                        <th style="width: 10%;" class="text-right">Amount</th>
                        <th style="width: 6%;" class="text-center">Disc</th>
                        <th style="width: 8%;" class="text-center">Tax Rate</th>
                        <th style="width: 10%;" class="text-right">Tax Amt</th>
                        <th style="width: 15%;" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order['items'] as $item)
                    <tr>
                        <td>004</td>
                        <td>{{ $item['product_name'] }}</td>
                        <td class="text-center">{{ $item['quantity'] }}</td>
                        <td class="text-right">{{ $shopSettings['currency'] }} {{ number_format($item['price'], 2) }}</td>
                        <td class="text-right">{{ $shopSettings['currency'] }} {{ number_format($item['total'], 2) }}</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-right">-</td>
                        <td class="text-right">{{ $shopSettings['currency'] }} {{ number_format($item['total'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Summary Totals -->
            <div class="totals-section">
                <div class="totals-spacer"></div>
                <div class="totals-box">
                    <div class="total-row">
                        <div class="total-label">Subtotal:</div>
                        <div class="total-value">{{ $shopSettings['currency'] }} {{ number_format($order['subtotal'], 2) }}</div>
                    </div>
                    <div class="total-row">
                        <div class="total-label">Total excluding tax:</div>
                        <div class="total-value">{{ $shopSettings['currency'] }} {{ number_format($order['subtotal'], 2) }}</div>
                    </div>
                    <div class="total-row">
                        <div class="total-label">Tax amount:</div>
                        <div class="total-value">{{ $shopSettings['currency'] }} {{ number_format($order['tax'], 2) }}</div>
                    </div>
                    <div class="total-row total-row-bold">
                        <div class="total-label">Total including tax:</div>
                        <div class="total-value">{{ $shopSettings['currency'] }} {{ number_format($order['total'], 2) }}</div>
                    </div>
                    <div class="total-row total-row-bold">
                        <div class="total-label">Total payable:</div>
                        <div class="total-value">{{ $shopSettings['currency'] }} {{ number_format($order['total'], 2) }}</div>
                    </div>
                </div>
            </div>

            <!-- Footer: Date of Validation, End Text -->
            <div class="footer-section">
                <div class="footer-left">
                    @if($order['myinvois_invoice'])
                    <div class="validation-date">
                        <span style="font-weight: 500;">Date of Validation:</span> {{ \Carbon\Carbon::parse($order['myinvois_invoice']['created_at'])->format('Y-m-d H:i:s') }}
                    </div>
                    @endif
                    <div class="end-text">End of e-Invoice</div>
                </div>
            </div>

            <!-- QR Code at the very end -->
            @if($order['qr_code_base64'])
            <div class="qr-code-section">
                <div class="qr-code">
                    <img src="{{ $order['qr_code_base64'] }}" alt="QR Code">
                    <div class="qr-code-text">Scan to verify</div>
                </div>
            </div>
            @endif
        </div>
    </div>
</body>
</html>
