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
    public $eInvoicePdf;

    public function __construct(Order $order, $pdf, $eInvoicePdf = null)
    {
        $this->order = $order;
        $this->pdf = $pdf;
        $this->eInvoicePdf = $eInvoicePdf;
        $this->settings = ShopSettings::first();
    }

    public function build()
    {
        // Subject to include shop name and order id
        $email = $this->subject($this->settings->shop_name . ' - Invoice #' . $this->order->id)
                    ->view('emails.invoice')
                    ->attachData($this->pdf, 'invoice.pdf', [
                        'mime' => 'application/pdf',
                    ]);

        // Attach e-invoice if available
        if ($this->eInvoicePdf) {
            $email->attachData($this->eInvoicePdf, 'e-invoice-' . $this->order->id . '.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $email;
    }
} 