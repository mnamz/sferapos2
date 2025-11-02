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
        $this->baseUrl = config('services.myinvois.base_url', 'https://myinvois.myrccornertrading.com');
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

    /**
     * Submit consolidated invoices (multiple orders in one request)
     */
    public function submitConsolidatedInvoices(\Illuminate\Support\Collection $orders): array
    {
        $results = [];
        $payload = ['documents' => []];

        foreach ($orders as $order) {
            $orderPayload = $this->prepareInvoicePayload($order);
            // Extract the document from the payload
            if (isset($orderPayload['documents'][0])) {
                $payload['documents'][] = $orderPayload['documents'][0];
            }
        }

        if (empty($payload['documents'])) {
            Log::error('MyInvois Consolidated - No documents to submit');
            return ['success' => false, 'message' => 'No documents to submit'];
        }

        try {
            Log::info('MyInvois Consolidated API Request', [
                'order_count' => count($payload['documents']),
                'order_ids' => $orders->pluck('id')->toArray()
            ]);

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ])
                ->post($this->baseUrl . '/documents/submit/invoice', $payload);

            $responseData = $response->json();
            $pushResult = [
                'timestamp' => now()->toDateTimeString(),
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'order_ids' => $orders->pluck('id')->toArray(),
                'order_count' => count($payload['documents']),
                'response' => $responseData,
                'submission_uid' => $responseData['submissionUid'] ?? null,
                'accepted_count' => count($responseData['acceptedDocuments'] ?? []),
                'rejected_count' => count($responseData['rejectedDocuments'] ?? []),
            ];

            // Save push result to file
            $this->savePushResult($pushResult);

            // Store invoice information for accepted documents
            if ($response->successful() && isset($responseData['acceptedDocuments'])) {
                foreach ($responseData['acceptedDocuments'] as $acceptedDoc) {
                    // Match by document ID (order ID) in the accepted document
                    $documentId = $acceptedDoc['id'] ?? null;
                    if ($documentId) {
                        $order = $orders->firstWhere('id', $documentId);
                        if ($order && !$order->myInvoisInvoice) {
                            $order->myInvoisInvoice()->create([
                                'submission_uid' => $responseData['submissionUid'] ?? null,
                                'uuid' => $acceptedDoc['uuid'] ?? null,
                                'invoice_code_number' => $acceptedDoc['invoiceCodeNumber'] ?? null,
                                'request_payload' => $payload,
                                'response_payload' => $responseData
                            ]);
                        }
                    }
                }
            }

            Log::info('MyInvois Consolidated API Response', [
                'success' => $response->successful(),
                'status' => $response->status(),
                'submission_uid' => $responseData['submissionUid'] ?? null,
                'accepted_count' => $pushResult['accepted_count'],
                'rejected_count' => $pushResult['rejected_count'],
            ]);

            return $pushResult;
        } catch (\Exception $e) {
            $pushResult = [
                'timestamp' => now()->toDateTimeString(),
                'success' => false,
                'status_code' => 0,
                'order_ids' => $orders->pluck('id')->toArray(),
                'order_count' => count($payload['documents']),
                'error' => $e->getMessage(),
            ];

            $this->savePushResult($pushResult);

            Log::error('MyInvois Consolidated API Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $pushResult;
        }
    }

    /**
     * Save push result to JSON file
     */
    protected function savePushResult(array $result): bool
    {
        try {
            $resultsFile = storage_path('app/myinvois_push_results.json');
            $results = [];

            if (file_exists($resultsFile)) {
                $content = file_get_contents($resultsFile);
                $results = json_decode($content, true) ?? [];
            }

            // Add new result at the beginning (most recent first)
            array_unshift($results, $result);

            // Keep only last 100 results
            $results = array_slice($results, 0, 100);

            // Ensure directory exists
            $dir = dirname($resultsFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents(
                $resultsFile,
                json_encode($results, JSON_PRETTY_PRINT)
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save push result', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get push results history
     */
    public function getPushResults(): array
    {
        $resultsFile = storage_path('app/myinvois_push_results.json');
        
        if (!file_exists($resultsFile)) {
            return [];
        }

        $content = file_get_contents($resultsFile);
        return json_decode($content, true) ?? [];
    }
} 