<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function generate(Order $order)
    {
        $pdf = PDF::loadView('pdf.invoice', [
            'order' => $order,
            'settings' => \App\Models\ShopSettings::first(),
        ]);

        return $pdf->stream("invoice-{$order->order_number}.pdf");
    }
} 