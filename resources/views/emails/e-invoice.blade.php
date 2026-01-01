<!DOCTYPE html>
<html>
<head>
    <title>E-Invoice #{{ $order->id }}</title>
</head>
<body>
    <h3>Dear {{ $customerName }},</h3>
    
    <p>Thank you for your business. Please find attached your e-invoice for your recent order.</p>
    
    <p>Order Details:</p>
    <ul>
        <li>Order Number: {{ $order->id }}</li>
        <li>Date: {{ $order->created_at->format('Y-m-d H:i:s') }}</li>
        <li>Total Amount: {{ $settings->currency }}{{ number_format($order->total, 2) }}</li>
        @if($order->myInvoisInvoice)
        <li>E-Invoice Code: {{ $order->myInvoisInvoice->invoice_code_number }}</li>
        <li>Status: Validated</li>
        @endif
    </ul>
    
    <p>This e-invoice has been validated and submitted to MyInvois. You can scan the QR code in the attached PDF to verify the invoice.</p>
    
    <p>If you have any questions, please don't hesitate to contact us.</p>
    
    <p>Best regards,<br>
    {{ $settings->shop_name }}</p>
</body>
</html>

