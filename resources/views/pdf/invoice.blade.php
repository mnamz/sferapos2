<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->order_number }}</title>
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
        <div class="header">
            <h1>{{ $settings->shop_name }}</h1>
            @if($settings->receipt_header)
                <p>{{ $settings->receipt_header }}</p>
            @endif
        </div>

        <div class="shop-info">
            <p>{{ $settings->shop_address }}</p>
            <p>Phone: {{ $settings->shop_phone }}</p>
            <p>Email: {{ $settings->shop_email }}</p>
            @if($settings->company_number)
                <p>Company No: {{ $settings->company_number }}</p>
            @endif
            @if($settings->tax_number)
                <p>Tax No: {{ $settings->tax_number }}</p>
            @endif
        </div>

        <div class="order-info">
            <h2>Invoice #{{ $order->order_number }}</h2>
            <p>Date: {{ $order->created_at->format($settings->date_format) }}</p>
            <p>Status: {{ ucfirst($order->status) }}</p>
            <p>Payment Status: {{ ucfirst($order->payment_status) }}</p>
            <p>Payment Method: {{ ucfirst($order->payment_method) }}</p>
            
            @if($order->customer)
            <div style="margin-top: 20px;">
                <h3>Customer Information</h3>
                <p>Name: {{ $order->customer->name }}</p>
                <p>Email: {{ $order->customer->email }}</p>
                <p>Phone: {{ $order->customer->phone }}</p>
                <p>Address: {{ $order->customer->address }}</p>
            </div>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $settings->currency }}{{ number_format($item->price, 2) }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $settings->currency }}{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td>{{ $settings->currency }}{{ number_format($order->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td>Tax ({{ $settings->tax_percentage }}%):</td>
                    <td>{{ $settings->currency }}{{ number_format($order->tax, 2) }}</td>
                </tr>
                @if($order->delivery_cost > 0)
                <tr>
                    <td>Delivery Cost:</td>
                    <td>{{ $settings->currency }}{{ number_format($order->delivery_cost, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td><strong>Total:</strong></td>
                    <td><strong>{{ $settings->currency }}{{ number_format($order->total, 2) }}</strong></td>
                </tr>
                <tr>
                    <td>Paid Amount:</td>
                    <td>{{ $settings->currency }}{{ number_format($order->paid_amount, 2) }}</td>
                </tr>
                @if($order->due_amount > 0)
                <tr>
                    <td>Due Amount:</td>
                    <td>{{ $settings->currency }}{{ number_format($order->due_amount, 2) }}</td>
                </tr>
                @endif
                @if($order->change_amount > 0)
                <tr>
                    <td>Change:</td>
                    <td>{{ $settings->currency }}{{ number_format($order->change_amount, 2) }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div class="footer">
            @if($settings->receipt_footer)
                <p>{{ $settings->receipt_footer }}</p>
            @endif
            @if($order->remarks)
                <p>Remarks: {{ $order->remarks }}</p>
            @endif
        </div>
    </div>
</body>
</html> 