<!DOCTYPE html>
<html>
<head>
    <title>Invoice #{{ $order->id }}</title>
</head>
<body>
    <h3>Dear {{ $order->customer->name }},</h3>
    
    <p>Thank you for your business. Please find attached the invoice for your recent order.</p>
    
    <p>Order Details:</p>
    <ul>
        <li>Order Number: {{ $order->id }}</li>
        <li>Date: {{ $order->created_at->format('Y-m-d H:i:s') }}</li>
        <li>Total Amount: {{ $settings->currency }}{{ number_format($order->total, 2) }}</li>
    </ul>
    
    <p>If you have any questions, please don't hesitate to contact us.</p>
    
    <p>Best regards,<br>
    {{ $settings->shop_name }}</p>
</body>
</html> 