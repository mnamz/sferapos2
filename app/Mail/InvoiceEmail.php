<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\ShopSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $settings;

    public function __construct(Order $order, $pdf)
    {
        $this->order = $order;
        $this->pdf = $pdf;
        $this->settings = ShopSettings::first();
    }

    public function build()
    {
        return $this->subject('Invoice #' . $this->order->order_number)
                    ->view('emails.invoice')
                    ->attachData($this->pdf, 'invoice.pdf', [
                        'mime' => 'application/pdf',
                    ]);
    }
} 