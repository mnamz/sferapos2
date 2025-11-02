<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->id }}</title>
    <style>
        @page {
            margin-top: 65mm;   /* space for fixed header on all pages - increased for better page 2+ header spacing */
            margin-bottom: 25mm; /* leave room for fixed footer */
            margin-left: 10mm;
            margin-right: 10mm;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 100%;
            margin-top: 5mm; /* additional push on first page only */
            margin-bottom: 25mm; /* ensure content doesn't overlap with fixed footer */
            padding-bottom: 10mm; /* additional spacing for safety */
        }
        
        /* Fixed Header */
        .header {
            position: fixed;
            top: -55mm;
            left: 0;
            right: 0;
            height: 35mm;
            background-color: white;
            z-index: 1000;
            border-bottom: 1px solid #ddd;
            padding: 5mm 10mm 0 10mm;
        }
        
        .header-content { padding: 0; }

        .header-row:after { content: ""; display: table; clear: both; }
        .header-col1 { float: left; width: 25%; text-align: left; }
        .header-col2 { float: left; width: 50%; text-align: center; padding-left: 0; }
        .header-col3 { float: left; width: 25%; text-align: right; }
        
        .header-col1 { }
        .header-col2 { }
        .header-col3 { }

        .invoice-detail-line { font-size: 10px; }
        .invoice-detail-line .label { font-weight: bold; }
        
        .header-logo {
            max-width: 150px;
            max-height: 70px;
            display: block;
            margin: 0; /* ensure left aligned */
        }
        
        .shop-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .shop-address {
            font-size: 10px;
            line-height: 1.3;
            margin-bottom: 5px;
        }
        
        .registration-no {
            font-size: 10px;
            font-weight: bold;
        }
        
        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .invoice-panel {
            border: none;
            width: 200px;
            margin-left: auto; /* stick to right side */
        }
        .invoice-panel .invoice-title {
            background: #000;
            color: #fff;
            text-align: center;
            padding: 3px 6px;
            margin: 0 0 4px 0;
            font-size: 16px;
        }
        
        .invoice-details-table {
            width: 100%;
            border-collapse: collapse;
            border: none; /* outer border handled by panel */
            font-size: 11px;
        }
        
        .invoice-details-table td {
            padding: 4px 6px;
            border: none; /* labels column unbordered */
        }
        .invoice-details-table td.value {
            border: 1px solid #000; /* only values boxed */
            background: #fff;
            width: 60%;
        }
        
        .invoice-details-table .label {
            font-weight: bold;
        }
        
        /* Fixed Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 18mm;
            background-color: white;
            z-index: 1000;
            border-top: 1px solid #ddd;
        }
        
        .footer-content {
            padding: 3mm 15mm;
            font-size: 10px;
            text-align: center;
        }

        /* Global compact styling for all tables */
        table th,
        table td {
            line-height: 1.1; /* Compact line spacing for all table cells */
        }
        
        .contact-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-top: 6px;
            table-layout: fixed; /* Fixed layout for 6 columns */
        }
        .contact-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: center;
            width: 16.67%; /* Equal width for 6 columns (100% / 6) */
        }
        
        /* Style label columns (1st, 3rd, 5th) with black background and white text */
        .contact-table td:nth-child(odd) {
            background-color: #000;
            color: #fff;
            font-weight: bold;
        }
        
        
        /* Customer Details */
        .customer-section {
            margin-bottom: 15px;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 5px;
            text-decoration: underline;
        }
        
        .customer-details {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .customer-info {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        
        .shipping-info {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        
        /* HR spacer for table on page 2+ */
        .table-spacer-hr {
            height: 15mm; /* Adjust this value to change spacing on page 2+ */
            border: none;
            margin: 0;
            padding: 0;
            display: block;
            page-break-inside: avoid;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table thead {
            display: table-header-group; /* Ensure header repeats on each page */
        }
        
        .items-table thead tr {
            page-break-inside: avoid;
            page-break-after: avoid;
        }
        
        .items-table th,
        .items-table td {
            padding: 3px 3px; /* Reduced from 5px for more compact look */
            border: 1px solid #000;
            text-align: center;
            font-size: 11px;
        }
        
        .items-table th {
            /* background-color: #f0f0f0; */
            color: #fff;
            background: #000;
            font-weight: bold;
        }
        
        .items-table tbody tr {
            page-break-inside: avoid;
        }
        
        .items-table .item-no {
            width: 8%;
        }
        
        .items-table .item-desc {
            width: 45%;
            text-align: center;
        }
        
        .items-table .qty {
            width: 10%;
        }
        
        .items-table .unit-price {
            width: 15%;
        }
        
        .items-table .discount {
            width: 10%;
        }
        
        .items-table .total {
            width: 12%;
        }
        
        /* Special Notes */
        .special-notes {
            margin-bottom: 15px;
            border: 1px solid #000;
            padding: 8px;
            page-break-inside: avoid;
        }
        
        .special-notes .label {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 5px;
        }
        
        .special-notes .content {
            font-size: 11px;
            min-height: 20px;
        }
        
        /* Payment Summary (dompdf-safe, not used for layout columns anymore) */
        .payment-summary { width: 100%; margin-bottom: 15px; }
        .payment-details { width: 100%; }
        .totals-table { 
            width: 100%; 
            page-break-inside: avoid;
        }
        
        .totals-table table {
            width: 100%;
            border-collapse: collapse;
            border: none; /* Remove outer border */
        }
        
        .totals-table td {
            padding: 3px 6px; /* Reduced for more compact look */
            font-size: 11px;
        }
        
        .totals-table .label {
            font-weight: bold;
            width: 60%;
            border: none; /* Remove border from label column */
        }
        
        .totals-table .amount {
            text-align: right;
            width: 40%;
            border-left: 1px solid #000;
            border-top: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        
        .grand-total {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        /* Bank Details */
        .bank-details {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .bank-details .title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 8px;
        }
        
        .bank-details table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        
        .bank-details td {
            padding: 3px 6px; /* Reduced for more compact look */
            border: 1px solid #000;
            font-size: 11px;
        }
        
        .bank-details .label {
            font-weight: bold;
            width: 40%;
        }
        
        .bank-details .value {
            width: 60%;
        }
        
        /* Authorization */
        .authorization {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .authorization .label {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 5px;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            width: 200px;
            height: 20px;
            margin: 10px 0;
        }
        
        .signature-name {
            font-weight: bold;
            font-size: 11px;
            margin-top: 5px;
        }
        
        
        .enquiry-contact {
            margin-top: 15px;
            font-size: 10px;
            text-align: center;
        }
        
        .enquiry-contact .instruction {
            margin-bottom: 10px;
            font-size: 10px;
        }
        
        .contact-grid {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        
        .contact-item {
            display: table-cell;
            text-align: center;
            padding: 5px;
        }
        
        .contact-label {
            background-color: #000;
            color: #fff;
            padding: 3px 6px;
            font-weight: bold;
            font-size: 9px;
            display: inline-block;
            margin-bottom: 2px;
        }
        
        .contact-value {
            font-size: 9px;
            display: block;
        }
        
        /* Utility Classes */
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        /* Top info tables (Buyer/Shipping) */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: -10mm; /* Negative margin to move table up - adjust as needed */
            margin-bottom: 12px;
            font-size: 11px;
        }
        .info-table th {
            background: #000;
            color: #fff;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 11px;
            padding: 4px 6px; /* Reduced for more compact look */
            text-align: left;
            border: 1px solid #000;
        }
        .info-table td {
            border: 1px solid #000;
            padding: 4px 6px; /* Reduced for more compact look */
        }
        .info-label {
            width: 25%;
            background: #f8f8f8;
            font-weight: bold;
        }
        
        /* Two-column layout table (dompdf-safe) */
        .two-col-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .two-col-table td {
            vertical-align: top;
            padding: 0 10px;
            border: none;
        }
        .two-col-left { width: 55%; padding-left: 0; padding-right: 10px; }
        .two-col-right { width: 45%; padding-right: 0; padding-left: 10px; }
        
        /* Bottom sections wrapper - keep totals and signatures together */
        .bottom-sections {
            page-break-inside: avoid;
            page-break-before: auto;
        }
        
        /* Blank table for custom design */
        .blank-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-top: 15px;
            table-layout: fixed; /* Fixed layout prevents resizing */
        }
        
        .blank-table td {
            border: none; /* No inner borders - only outer table border */
            padding: 3px 6px; /* Reduced padding for compact look */
            width: 50%; /* Equal 50/50 width for both columns */
            line-height: 1.1; /* Consistent line height */
            min-height: 8mm; /* Reduced height for compact look */
            vertical-align: top; /* Aligns content to top */
            font-size: 11px; /* Consistent font size */
        }
        
        /* Add vertical border between left and right columns */
        .blank-table td:first-child {
            border-right: 1px solid #000;
        }
    </style>
</head>
<body>
    <!-- Fixed Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-row">
                <div class="header-col1">
                    @php
                        $logoPath = null;
                        if (!empty($settings->invoice_logo_path)) {
                            $logoPath = public_path('storage/'.$settings->invoice_logo_path);
                        } elseif (!empty($settings->logo_path)) {
                            $logoPath = public_path('storage/'.$settings->logo_path);
                        }
                        $logoDataUri = null;
                        if ($logoPath && file_exists($logoPath)) {
                            $mime = function_exists('mime_content_type') ? mime_content_type($logoPath) : 'image/png';
                            $logoDataUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoPath));
                        }
                    @endphp
                    @if($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="Logo" class="header-logo">
                    @endif
                </div>
                <div class="header-col2">
                    <div class="shop-name">{{ $settings->shop_name ?? 'DREAM STREET RESTORATION' }}</div>
                    <div class="shop-address">{{ $settings->shop_address ?? '' }}</div>
                </div>
                <div class="header-col3">
                    <div class="invoice-panel">
                        <div class="invoice-title">INVOICE</div>
                        <table class="invoice-details-table">
                            <tr>
                                <td class="label">Invoice No:</td>
                                <td class="value">{{ $order->formatted_invoice_number }}</td>
                            </tr>
                            <tr>
                                <td class="label">Date Issued:</td>
                                <td class="value">{{ $order->created_at->format('d/m/Y') }}</td>
                            </tr>
                            <tr>
                                <td class="label">Ref ID:</td>
                                <td class="value"></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fixed Footer -->
    <div class="footer">
        <div class="footer-content">
            <div class="enquiry-contact">
                <div class="instruction">Should you have any enquiries, please forward it to the contact person stated below.</div>
                <table class="contact-table">
                    <tr>
                        <td>NAME</td>
                        <td>Dharveen Suria</td>
                        <td>TEL. NO</td>
                        <td>{{ $settings->shop_phone ?? '' }}</td>
                        <td>EMAIL</td>
                        <td>{{ $settings->shop_email ?? '' }}</td>
                    </tr>
                </table>
            </div>
        </div>
        </div>

    <div class="container">

        <!-- Customer/Buyer Details and Shipping (Three-column table: Label | Buyer | Shipping) -->
        <table class="info-table">
            <tr>
                <th style="width: 20%; background:#fff; border: none"></th>
                <th style="width: 40%">Buyer/Customer/Client Details</th>
                <th style="width: 40%">Shipping & Delivery Information</th>
            </tr>
            <tr>
                <td class="info-label">Name</td>
                <td>{{ optional($order->customer)->name ?? '-' }}</td>
                <td>{{ $order->delivery_name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Company Name</td>
                <td>{{ optional($order->customer)->company_name ?? '-' }}</td>
                <td>{{ $order->delivery_company_name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Address</td>
                <td>{{ optional($order->customer)->address ?? '-' }}</td>
                <td>{{ $order->delivery_address ?? '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Tel No.</td>
                <td>{{ optional($order->customer)->phone ?? '-' }}</td>
                <td>{{ $order->delivery_phone ?? '-' }}</td>
            </tr>
        </table>

        <!-- Itemized List Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="item-no">NO.</th>
                    <th class="item-desc">ITEM DESCRIPTION</th>
                    <th class="qty">QTY</th>
                    <th class="unit-price">U/PRICE</th>
                    <th class="discount">DIS</th>
                    <th class="total">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="item-desc">{{ $item->product_name }} {{ $item->remark ? '('.$item->remark.')' : '' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->price, 2) }}</td>
                    <td>{{ number_format($item->discount ?? 0, 2) }}</td>
                    <td>{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Bottom Sections: Special Notes, Totals, Signature, and Bank Details -->
        <div class="bottom-sections" style="margin-top: 15px;">
            <!-- Special Notes & Totals Side by Side -->
            <table class="two-col-table">
                <tr>
                    <td class="two-col-left">
                        <div class="special-notes">
                            <div class="label">SPECIAL NOTES & INSTRUCTIONS</div>
                            <div class="content">ATIVA (KFQ 2903)</div>
                        </div>
                    </td>
                    <td class="two-col-right">
                        <div class="totals-table">
                            <table>
                            <tr>
                                <td class="label">Paid:</td>
                                <td class="amount">{{ $settings->currency ?? 'MYR' }} {{ number_format($order->paid_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="label">Due:</td>
                                <td class="amount">{{ $settings->currency ?? 'MYR' }} {{ number_format($order->due_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="label">Sub-Total:</td>
                                <td class="amount">{{ $settings->currency ?? 'MYR' }} {{ number_format($order->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="label">Discount:</td>
                                <td class="amount">{{ $settings->currency ?? 'MYR' }} {{ number_format($order->discount ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="label">Tax Rate:</td>
                                <td class="amount">{{ number_format($settings->tax_percentage ?? 0, 2) }}%</td>
                            </tr>
                            <tr>
                                <td class="label">Tax Amount:</td>
                                <td class="amount">{{ $settings->currency ?? 'MYR' }} {{ number_format($order->tax, 2) }}</td>
                            </tr>
                            <tr class="grand-total">
                                <td class="label">Grand Total:</td>
                                <td class="amount">{{ $settings->currency ?? 'MYR' }} {{ number_format($order->total, 2) }}</td>
                            </tr>
                        </table>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Blank Table for Custom Design -->
            <table class="blank-table">
                <tr>
                    <td></td>
                    <td>BANK DETAILS</td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td>Account Holder:</td>
                </tr>
                <tr>
                    <td></td>
                    <td>Dream Street Restoration Services</td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td>Account Number:</td>
                </tr>
                <tr>
                    <td></td>
                    <td>8604351269</td>
                </tr>
                <tr>
                    <td>Issuer Authorized Signature</td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>ADMIN</strong></td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td>Date: {{ $order->created_at->format('d/m/Y') }}</td>
                </tr>
            </table>
        </div>

    </div>
</body>
</html> 