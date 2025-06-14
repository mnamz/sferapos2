<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShopSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyInvoisService
{
    protected $baseUrl;
    protected $settings;

    public function __construct()
    {
        $this->baseUrl = 'https://myinvois.myrccornertrading.com';
        $this->settings = ShopSettings::first();
    }

    public function submitInvoice(Order $order)
    {
        try {
            $payload = $this->prepareInvoicePayload($order);
            
            // Log the request payload
            Log::info('MyInvois API Request', [
                'order_id' => $order->id,
                'payload' => $payload
            ]);
            
            $response = Http::withoutVerifying() // Disable SSL verification for development
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->post($this->baseUrl . '/documents/submit/invoice', $payload);
            
            // Log the response
            Log::info('MyInvois API Response', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if we have accepted documents
                if (!isset($responseData['acceptedDocuments']) || empty($responseData['acceptedDocuments'])) {
                    Log::error('MyInvois API Error - No accepted documents', [
                        'order_id' => $order->id,
                        'response' => $responseData
                    ]);
                    return false;
                }

                $acceptedDoc = $responseData['acceptedDocuments'][0];
                
                // Store the invoice information
                $order->myInvoisInvoice()->create([
                    'submission_uid' => $responseData['submissionUid'] ?? null,
                    'uuid' => $acceptedDoc['uuid'] ?? null,
                    'invoice_code_number' => $acceptedDoc['invoiceCodeNumber'] ?? null,
                    'request_payload' => $payload,
                    'response_payload' => $responseData
                ]);

                Log::info('MyInvois Invoice Created', [
                    'order_id' => $order->id,
                    'submission_uid' => $responseData['submissionUid'] ?? null,
                    'invoice_code_number' => $acceptedDoc['invoiceCodeNumber'] ?? null
                ]);

                return true;
            }

            Log::error('MyInvois API Error', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('MyInvois API Exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    protected function prepareInvoicePayload(Order $order)
    {
        // Use the order's created_at timestamp in UTC and subtract 2 minutes
        $issueDate = $order->created_at->setTimezone('UTC');
        
        return [
            'documents' => [
                [
                    'id' => (string) $order->id,
                    'issueDate' => $issueDate->format('Y-m-d'),
                    'issueTime' => $issueDate->format('H:i:s\Z'), // Match exact format: "05:25:00Z"
                    'documentCurrencyCode' => 'MYR',
                    'supplier' => [
                        'TIN' => $this->settings->tax_number ?? 'IG50598793070',
                        'legalName' => $this->settings->shop_name,
                        'identificationNumber' => $this->settings->company_number ?? '010810101477',
                        'identificationScheme' => 'NRIC',
                        'telephone' => $this->settings->shop_phone,
                        'industryClassificationCode' => '01111',
                        'industryClassificationName' => 'Growing of maize',
                        'address' => [
                            'addressLines' => [
                                $this->settings->shop_address
                            ],
                            'cityName' => 'Kuala Lumpur',
                            'postalZone' => '50480',
                            'countrySubentityCode' => '14',
                            'countryCode' => 'MYS'
                        ]
                    ],
                    'customer' => [
                        'TIN' => 'EI00000000010',
                        'legalName' => $order->customer ? $order->customer->name : 'Walk-in Customer',
                        'identificationNumber' => $order->customer ? $order->customer->id : '000000',
                        'identificationScheme' => 'BRN',
                        'telephone' => $order->customer ? $order->customer->phone : '+60123456789',
                        'address' => [
                            'addressLines' => [
                                $order->customer ? $order->customer->address : 'Walk-in Customer'
                            ],
                            'cityName' => 'Kuala Lumpur',
                            'postalZone' => '50480',
                            'countrySubentityCode' => '14',
                            'countryCode' => 'MYS'
                        ]
                    ],
                    'invoiceLines' => $this->prepareInvoiceLines($order),
                    'taxTotal' => $this->prepareTaxTotal($order),
                    'legalMonetaryTotal' => $this->prepareMonetaryTotal($order)
                ]
            ]
        ];
    }

    protected function prepareInvoiceLines(Order $order)
    {
        return $order->items->map(function ($item, $index) {
            $taxAmount = (float)($item->price * $item->quantity) * ($this->settings->tax_percentage / 100);
            $subtotal = (float)($item->price * $item->quantity);
            
            return [
                'id' => (string) ($index + 1),
                'quantity' => (int)$item->quantity,
                'unitPrice' => (float)$item->price,
                'unitCode' => 'XUN',
                'subtotal' => $subtotal,
                'itemDescription' => $item->product_name,
                'itemCommodityClassification' => [
                    'code' => '001',
                    'listID' => 'CLASS'
                ],
                'lineTaxTotal' => [
                    'taxAmount' => $taxAmount,
                    'taxSubtotals' => [
                        [
                            'taxableAmount' => $subtotal,
                            'taxAmount' => $taxAmount,
                            'taxCategoryCode' => '01',
                            'percent' => (float)$this->settings->tax_percentage
                        ]
                    ]
                ]
            ];
        })->toArray();
    }

    protected function prepareTaxTotal(Order $order)
    {
        return [
            'totalTaxAmount' => (float)$order->tax,
            'taxSubtotals' => [
                [
                    'taxableAmount' => (float)$order->subtotal,
                    'taxAmount' => (float)$order->tax,
                    'taxCategoryCode' => '01',
                    'percent' => (float)$this->settings->tax_percentage
                ]
            ]
        ];
    }

    protected function prepareMonetaryTotal(Order $order)
    {
        return [
            'lineExtensionAmount' => (float)$order->subtotal,
            'taxExclusiveAmount' => (float)$order->subtotal,
            'taxInclusiveAmount' => (float)$order->total,
            'payableAmount' => (float)$order->total
        ];
    }
} 