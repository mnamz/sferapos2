<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceEmail;
use App\Models\ShopSettings;

class InvoiceController extends Controller
{
    public function generate(Order $order)
    {
        $pdf = PDF::loadView('pdf.invoice', [
            'order' => $order,
            'settings' => \App\Models\ShopSettings::first(),
        ]);

        return $pdf->stream("invoice-{$order->id}.pdf");
    }

    public function send(Order $order)
    {
        if (!$order->customer || !$order->customer->email) {
            return back()->with('error', 'Customer email not found.');
        }

        $settings = ShopSettings::first();
        $pdf = PDF::loadView('pdf.invoice', [
            'order' => $order,
            'settings' => $settings
        ]);

        Mail::to($order->customer->email)
            ->send(new InvoiceEmail($order, $pdf->output()));

        return back()->with('success', 'Invoice sent successfully.');
    }
} 