<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\ShopSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EInvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $settings;
    public $customerName;

    public function __construct(Order $order, $pdf, $customerName = null)
    {
        $this->order = $order;
        $this->pdf = $pdf;
        $this->settings = ShopSettings::first();
        $this->customerName = $customerName ?? ($order->customer ? $order->customer->name : 'Customer');
    }

    public function build()
    {
        // Subject to include shop name and order id
        return $this->subject($this->settings->shop_name . ' - E-Invoice #' . $this->order->id)
                    ->view('emails.e-invoice')
                    ->attachData($this->pdf, 'e-invoice-' . $this->order->id . '.pdf', [
                        'mime' => 'application/pdf',
                    ]);
    }
}

