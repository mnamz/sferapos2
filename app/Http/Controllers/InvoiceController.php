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
        $order->load('myInvoisQueue');
        $settings = ShopSettings::first();
        
        // Check if order is queued for pushing
        $qrCodeBase64 = null;
        $isQueued = $order->myInvoisQueue && $order->myInvoisQueue->status === 'pending';
        
        if ($isQueued) {
            $claimUrl = config('services.myinvois.einvoice_claim_url', 'https://einvoice.myrccornertrading.com');
            $branch = config('services.myinvois.branch', '');
            $qrCodeUrl = $claimUrl . '?order_id=' . $order->id . ($branch ? '&branch=' . urlencode($branch) : '');
            
            // Generate QR code as base64 for PDF
            try {
                $qrCodeImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrCodeUrl);
                $qrCodeImage = file_get_contents($qrCodeImageUrl);
                if ($qrCodeImage) {
                    $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($qrCodeImage);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to generate QR code for invoice PDF', ['error' => $e->getMessage()]);
            }
        }
        
        $pdf = PDF::loadView('pdf.invoice', [
            'order' => $order,
            'settings' => $settings,
            'isQueued' => $isQueued,
            'qrCodeBase64' => $qrCodeBase64,
        ]);

        return $pdf->stream("invoice-{$order->id}.pdf");
    }

    public function send(Order $order)
    {
        if (!$order->customer || !$order->customer->email) {
            return back()->with('error', 'Customer email not found.');
        }

        $order->load(['customer', 'user', 'items.product', 'myInvoisInvoice', 'myInvoisQueue']);

        $settings = ShopSettings::first();
        
        // Check if order is queued for pushing
        $qrCodeBase64 = null;
        $isQueued = $order->myInvoisQueue && $order->myInvoisQueue->status === 'pending';
        
        if ($isQueued) {
            $claimUrl = config('services.myinvois.einvoice_claim_url', 'https://einvoice.myrccornertrading.com');
            $branch = config('services.myinvois.branch', '');
            $qrCodeUrl = $claimUrl . '?order_id=' . $order->id . ($branch ? '&branch=' . urlencode($branch) : '');
            
            // Generate QR code as base64 for PDF
            try {
                $qrCodeImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrCodeUrl);
                $qrCodeImage = file_get_contents($qrCodeImageUrl);
                if ($qrCodeImage) {
                    $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($qrCodeImage);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to generate QR code for invoice PDF', ['error' => $e->getMessage()]);
            }
        }
        
        $pdf = PDF::loadView('pdf.invoice', [
            'order' => $order,
            'settings' => $settings,
            'isQueued' => $isQueued,
            'qrCodeBase64' => $qrCodeBase64,
        ]);

        // Generate e-invoice PDF if order has been pushed to MyInvois
        $eInvoicePdf = null;
        if ($order->myInvoisInvoice) {
            try {
                $myInvoisService = app(\App\Services\MyInvoisService::class);
                $myInvoisInvoice = $order->myInvoisInvoice;
                $qrCodeUrl = null;
                $documentDetails = null;

                $documentDetails = $myInvoisService->getDocumentDetails($myInvoisInvoice->uuid);
                
                $longId = null;
                if ($documentDetails && isset($documentDetails['longId'])) {
                    $longId = $documentDetails['longId'];
                } elseif ($myInvoisInvoice->response_payload && isset($myInvoisInvoice->response_payload['longId'])) {
                    $longId = $myInvoisInvoice->response_payload['longId'];
                }
                
                if ($longId && $myInvoisInvoice->uuid) {
                    $qrCodeUrl = $myInvoisService->generateQrCodeUrl(
                        $myInvoisInvoice->uuid,
                        $longId
                    );
                }

                $orderData = [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer' => $order->customer ? [
                        'id' => $order->customer->id,
                        'name' => $order->customer->name,
                        'email' => $order->customer->email,
                        'phone' => $order->customer->phone,
                        'address' => $order->customer->address,
                        'city' => $order->customer->city,
                        'postal_code' => $order->customer->postal_code,
                        'state_code' => $order->customer->state_code,
                        'country' => $order->customer->country,
                        'tin' => $order->customer->tin,
                        'brn' => $order->customer->brn,
                        'nric' => $order->customer->nric,
                    ] : null,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'total' => $item->total,
                            'profit' => $item->profit,
                            'remark' => $item->remark,
                        ];
                    }),
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'delivery_cost' => $order->delivery_cost,
                    'discount' => $order->discount,
                    'total' => $order->total,
                    'created_at' => $order->created_at,
                    'myinvois_invoice' => [
                        'uuid' => $myInvoisInvoice->uuid,
                        'invoice_code_number' => $myInvoisInvoice->invoice_code_number,
                        'submission_uid' => $myInvoisInvoice->submission_uid,
                        'created_at' => $myInvoisInvoice->created_at,
                    ],
                    'qr_code_url' => $qrCodeUrl,
                    'qr_code_base64' => null,
                ];

                // Generate QR code as base64 for PDF
                if ($qrCodeUrl) {
                    try {
                        $qrCodeImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . urlencode($qrCodeUrl);
                        $qrCodeImage = file_get_contents($qrCodeImageUrl);
                        if ($qrCodeImage) {
                            $orderData['qr_code_base64'] = 'data:image/png;base64,' . base64_encode($qrCodeImage);
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Failed to generate QR code for PDF', ['error' => $e->getMessage()]);
                    }
                }

                $shopSettings = [
                    'shop_name' => $settings->shop_name ?? '',
                    'shop_address' => $settings->shop_address ?? '',
                    'shop_phone' => $settings->shop_phone ?? '',
                    'shop_email' => $settings->shop_email ?? '',
                    'tax_number' => $settings->tax_number ?? '',
                    'identification_number' => $settings->identification_number ?? '',
                    'identification_scheme' => $settings->identification_scheme ?? '',
                    'industry_classification_code' => $settings->industry_classification_code ?? '',
                    'industry_classification_name' => $settings->industry_classification_name ?? '',
                    'currency' => $settings->currency ?? 'RM',
                ];

                $eInvoicePdf = PDF::loadView('pdf.e-invoice', [
                    'order' => $orderData,
                    'shopSettings' => $shopSettings,
                ])->setPaper('a4', 'portrait')->output();
            } catch (\Exception $e) {
                \Log::error('Failed to generate e-invoice PDF for email', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Reload order with myInvoisInvoice relationship for email
        $order->load('myInvoisInvoice');
        
        Mail::to($order->customer->email)
            ->send(new InvoiceEmail($order, $pdf->output(), $eInvoicePdf));

        return back()->with('success', 'Invoice sent successfully' . ($eInvoicePdf ? ' with e-invoice attached' : '') . '.');
    }
} 