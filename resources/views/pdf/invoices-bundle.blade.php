<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoices</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }
        .container { padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
        .invoice-page { page-break-after: always; }
        .invoice-page:last-child { page-break-after: auto; }
    </style>
</head>
<body>
    @foreach($orders as $order)
        <div class="invoice-page">
            @include('pdf.partials.invoice-body', [
                'order' => $order,
                'settings' => $settings,
                'isQueued' => false,
                'qrCodeBase64' => null,
                'queueDelayHours' => $queueDelayHours,
            ])
        </div>
    @endforeach
</body>
</html>
