<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShopSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyInvoisService
{
    protected $baseUrl;

    protected $apiKey;

    protected $settings;

    protected $enabled;

    public function __construct()
    {
        $this->baseUrl = config('services.myinvois.base_url');
        $this->apiKey = config('services.myinvois.api_key');
        $this->enabled = config('services.myinvois.enabled', false);
        $this->settings = ShopSettings::first();
    }

    /**
     * Standard headers for middleware requests. The middleware authenticates
     * via an X-API-Key header; it is only sent when MYINVOIS_API_KEY is set.
     */
    protected function requestHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (filled($this->apiKey)) {
            $headers['X-API-Key'] = $this->apiKey;
        }

        return $headers;
    }

    /**
     * Determine if the service is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && filled($this->baseUrl);
    }

    /**
     * LHDN allows cancellation only within a fixed window (default 72h) of validation.
     * We approximate validation time with the local submission timestamp.
     */
    public function isWithinCancellationWindow(\App\Models\MyInvoisInvoice $invoice): bool
    {
        $hours = (int) config('services.myinvois.cancellation_window_hours', 72);

        return $invoice->created_at->gt(now()->subHours($hours));
    }

    /**
     * Public wrapper so callers/tests can inspect the payload.
     */
    public function buildInvoicePayload(Order $order, ?array $customCustomerInfo = null): array
    {
        return $this->prepareInvoicePayload($order, $customCustomerInfo);
    }

    /**
     * Format phone number to match MyInvois regex: ^\+[1-9]\d{1,14}$
     * Requirements:
     * - Must start with +
     * - First digit after + must be 1-9 (not 0)
     * - Total length: 2-15 digits
     *
     * @param  string  $default  Default phone if invalid/empty
     */
    protected function formatPhoneNumber(?string $phone, string $default = '+60123456789'): string
    {
        if (empty($phone)) {
            return $default;
        }

        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);

        // If doesn't start with +, add it
        if (! str_starts_with($cleaned, '+')) {
            // Remove leading zeros if any
            $cleaned = ltrim($cleaned, '0');
            $cleaned = '+'.$cleaned;
        }

        // Ensure first digit after + is 1-9
        if (strlen($cleaned) > 1 && $cleaned[1] === '0') {
            // Replace leading 0 after + with a valid digit (use 6 for Malaysia)
            $cleaned = '+'.'6'.substr($cleaned, 2);
        }

        // Validate against regex: ^\+[1-9]\d{1,14}$
        if (preg_match('/^\+[1-9]\d{1,14}$/', $cleaned)) {
            return $cleaned;
        }

        // If validation fails, return default
        return $default;
    }

    /**
     * Queue an invoice for later submission (72 hours)
     */
    public function queueInvoice(Order $order)
    {
        if (! $this->isEnabled()) {
            Log::warning('MyInvois service is disabled or not configured.');

            return false;
        }

        try {
            $payload = $this->prepareInvoicePayload($order);

            // Store in queue
            \App\Models\MyInvoisQueue::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'invoice_payload' => $payload,
                    'status' => 'pending',
                ]
            );

            Log::info('MyInvois invoice queued', ['order_id' => $order->id]);

            return true;
        } catch (\Exception $e) {
            Log::error('MyInvois Queue Exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Submit invoice immediately (for manual push or 72+ hour old invoices)
     *
     * @param  bool  $forceRefresh  If true, regenerate payload from current order data instead of using queue
     * @param  array|null  $customCustomerInfo  Optional custom customer info to override order customer data
     */
    public function submitInvoice(Order $order, bool $forceRefresh = false, ?array $customCustomerInfo = null)
    {
        if (! $this->isEnabled()) {
            Log::warning('MyInvois service is disabled or not configured.');

            return false;
        }

        try {
            // Get payload from queue if exists and not forcing refresh, otherwise generate new
            $queueItem = $order->myInvoisQueue;
            if ($forceRefresh || ! $queueItem || $queueItem->status !== 'pending' || $customCustomerInfo) {
                $payload = $this->prepareInvoicePayload($order, $customCustomerInfo);

                // Update queue with fresh payload if queue exists
                if ($queueItem && $queueItem->status === 'pending') {
                    $queueItem->update(['invoice_payload' => $payload]);
                }
            } else {
                $payload = $queueItem->invoice_payload;
            }

            // Log the request payload
            Log::info('MyInvois API Request', [
                'order_id' => $order->id,
                'payload' => $payload,
            ]);

            $response = Http::withoutVerifying() // Disable SSL verification for development
                ->withHeaders($this->requestHeaders())
                ->post($this->baseUrl.'/documents/submit/invoice', $payload);

            // Log the response
            Log::info('MyInvois API Response', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // Check if we have accepted documents
                if (! isset($responseData['acceptedDocuments']) || empty($responseData['acceptedDocuments'])) {
                    Log::error('MyInvois API Error - No accepted documents', [
                        'order_id' => $order->id,
                        'response' => $responseData,
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
                    'response_payload' => $responseData,
                ]);

                // Update queue status if exists
                if ($queueItem) {
                    $queueItem->update([
                        'status' => 'pushed',
                        'myinvois_id' => $acceptedDoc['uuid'] ?? null,
                        'pushed_at' => now(),
                    ]);
                }

                Log::info('MyInvois Invoice Created', [
                    'order_id' => $order->id,
                    'submission_uid' => $responseData['submissionUid'] ?? null,
                    'invoice_code_number' => $acceptedDoc['invoiceCodeNumber'] ?? null,
                ]);

                return true;
            }

            Log::error('MyInvois API Error', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('MyInvois API Exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Cancel an invoice on MyInvois
     */
    public function cancelInvoice(string $myinvoisId, string $reason)
    {
        if (! $this->isEnabled()) {
            Log::warning('MyInvois service is disabled or not configured.');

            return false;
        }

        try {
            $url = $this->baseUrl.'/documents/'.$myinvoisId.'/cancel?reason='.urlencode($reason);

            Log::info('MyInvois Cancel Request', [
                'myinvois_id' => $myinvoisId,
                'reason' => $reason,
                'url' => $url,
            ]);

            $response = Http::withoutVerifying()
                ->withHeaders($this->requestHeaders())
                ->put($url);

            Log::info('MyInvois Cancel Response', [
                'myinvois_id' => $myinvoisId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('MyInvois Cancel Exception', [
                'myinvois_id' => $myinvoisId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Submit a Credit Note e-invoice reversing the order's active e-invoice.
     * LHDN procedure once the 72h cancellation window has lapsed: credit note
     * first, then reissue a corrected invoice. On success the original row is
     * marked 'credited' and a MyInvoisCreditNote record is stored.
     */
    public function submitCreditNote(Order $order, string $reason)
    {
        if (! $this->isEnabled()) {
            Log::warning('MyInvois service is disabled or not configured.');

            return false;
        }

        $invoice = $order->myInvoisInvoice;
        if (! $invoice) {
            Log::warning('MyInvois credit note requested but no active invoice', ['order_id' => $order->id]);

            return false;
        }

        try {
            $payload = $this->prepareCreditNotePayload($order, $invoice);

            Log::info('MyInvois Credit Note Request', [
                'order_id' => $order->id,
                'original_uuid' => $invoice->uuid,
                'payload' => $payload,
            ]);

            $response = Http::withoutVerifying()
                ->withHeaders($this->requestHeaders())
                ->post($this->baseUrl.'/documents/submit/credit-note', $payload);

            Log::info('MyInvois Credit Note Response', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            if (! $response->successful()) {
                return false;
            }

            $responseData = $response->json();
            if (empty($responseData['acceptedDocuments'])) {
                Log::error('MyInvois Credit Note Error - No accepted documents', [
                    'order_id' => $order->id,
                    'response' => $responseData,
                ]);

                return false;
            }

            $acceptedDoc = $responseData['acceptedDocuments'][0];

            DB::transaction(function () use ($order, $invoice, $responseData, $acceptedDoc, $reason, $payload) {
                \App\Models\MyInvoisCreditNote::create([
                    'order_id' => $order->id,
                    'myinvois_invoice_id' => $invoice->id,
                    'submission_uid' => $responseData['submissionUid'] ?? null,
                    'uuid' => $acceptedDoc['uuid'] ?? null,
                    'credit_note_code_number' => $acceptedDoc['invoiceCodeNumber'] ?? null,
                    'reason' => $reason,
                    'request_payload' => $payload,
                    'response_payload' => $responseData,
                ]);

                $invoice->update(['status' => 'credited']);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('MyInvois Credit Note Exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Credit note payload: same shape as the invoice document, issued now,
     * with a billing reference back to the original validated e-invoice.
     */
    protected function prepareCreditNotePayload(Order $order, \App\Models\MyInvoisInvoice $invoice): array
    {
        $payload = $this->prepareInvoicePayload($order);
        $document = $payload['documents'][0];

        $originalInternalId = $invoice->request_payload['documents'][0]['id']
            ?? $invoice->invoice_code_number;

        $priorCreditNotes = \App\Models\MyInvoisCreditNote::where('order_id', $order->id)->count();
        $suffix = $priorCreditNotes === 0 ? '' : '-'.($priorCreditNotes + 1);

        $now = now()->setTimezone('UTC');
        $document['id'] = 'CN'.$originalInternalId.$suffix;
        $document['issueDate'] = $now->format('Y-m-d');
        $document['issueTime'] = $now->format('H:i:s\Z');
        $document['billingReferences'] = [
            [
                'uuid' => $invoice->uuid,
                'internalId' => $originalInternalId,
            ],
        ];

        return ['documents' => [$document]];
    }

    /**
     * Get MyInvois document details by UUID
     */
    public function getDocumentDetails(string $uuid)
    {
        if (! $this->isEnabled()) {
            Log::warning('MyInvois service is disabled or not configured.');

            return null;
        }

        try {
            $url = $this->baseUrl.'/documents/'.$uuid;

            $response = Http::withoutVerifying()
                ->withHeaders($this->requestHeaders())
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MyInvois Get Document Failed', [
                'uuid' => $uuid,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('MyInvois Get Document Exception', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate QR code URL for MyInvois invoice
     */
    public function generateQrCodeUrl(string $uuid, string $longId): string
    {
        return "https://myinvois.hasil.gov.my/{$uuid}/share/{$longId}";
    }

    protected function prepareInvoicePayload(Order $order, ?array $customCustomerInfo = null)
    {
        // Ensure order has an ID - use order_number as fallback if ID is missing
        $orderId = $order->id ?? $order->order_number ?? null;
        if (! $orderId) {
            throw new \Exception('Order ID or order number is required for MyInvois submission');
        }

        // Use the order's created_at timestamp in UTC, or current time if not set
        $issueDate = $order->created_at
            ? $order->created_at->setTimezone('UTC')
            : now()->setTimezone('UTC');

        return [
            'documents' => [
                [
                    'id' => $this->documentId($order),
                    'issueDate' => $issueDate->format('Y-m-d'),
                    'issueTime' => $issueDate->format('H:i:s\Z'), // Match exact format: "05:25:00Z"
                    'documentCurrencyCode' => 'MYR',
                    'supplier' => [
                        'TIN' => $this->settings->tax_number ?? 'IG50598793070',
                        'legalName' => $this->settings->shop_name,
                        'identificationNumber' => $this->settings->identification_number ?? $this->settings->company_number ?? '010810101477',
                        'identificationScheme' => $this->settings->identification_scheme ?? 'NRIC',
                        'telephone' => $this->formatPhoneNumber($this->settings->shop_phone),
                        'industryClassificationCode' => $this->settings->industry_classification_code ?? '01111',
                        'industryClassificationName' => $this->settings->industry_classification_name ?? 'Growing of maize',
                        'address' => [
                            'addressLines' => [
                                $this->settings->shop_address,
                            ],
                            'cityName' => 'Kuala Lumpur',
                            'postalZone' => '50480',
                            'countrySubentityCode' => '14',
                            'countryCode' => 'MYS',
                        ],
                    ],
                    'customer' => $customCustomerInfo ? $this->prepareCustomCustomerInfo($customCustomerInfo) : $this->prepareCustomerInfo($order),
                    'invoiceLines' => $this->prepareInvoiceLines($order, $customCustomerInfo),
                    'taxTotal' => $this->prepareTaxTotal($order),
                    'legalMonetaryTotal' => $this->prepareMonetaryTotal($order),
                ],
            ],
        ];
    }

    /**
     * MyInvois rejects duplicate internal document IDs per supplier, so a
     * reissue after cancellation/credit note needs a unique suffix.
     */
    protected function documentId(Order $order): string
    {
        $branch = config('services.myinvois.branch', '');
        $base = (string) ($order->id ?? $order->order_number).'-'.$branch;
        $priorSubmissions = \App\Models\MyInvoisInvoice::where('order_id', $order->id)->count();

        return $priorSubmissions === 0 ? $base : $base.'-R'.$priorSubmissions;
    }

    protected function prepareCustomerInfo(Order $order)
    {
        $customer = $order->customer;

        // Default values for walk-in customers
        if (! $customer) {
            return [
                'TIN' => 'EI00000000010',
                'legalName' => 'Walk-in Customer',
                'identificationNumber' => 'NA',
                'identificationScheme' => 'BRN',
                'telephone' => $this->formatPhoneNumber(null),
                'address' => [
                    'addressLines' => ['Walk-in Customer'],
                    'cityName' => 'Kuala Lumpur',
                    'postalZone' => '50480',
                    'countrySubentityCode' => '14',
                    'countryCode' => 'MYS',
                ],
            ];
        }

        // Determine TIN: Use customer's TIN if available, otherwise use default
        $tin = $customer->tin ?: 'EI00000000010';

        // Determine identification scheme: BRN takes priority, then NRIC
        if ($customer->brn) {
            $identificationScheme = 'BRN';
            $identificationNumber = $customer->brn;
        } elseif ($customer->nric) {
            $identificationScheme = 'NRIC';
            $identificationNumber = $customer->nric;
        } else {
            // If neither BRN nor NRIC provided, use default
            $identificationScheme = 'BRN';
            $identificationNumber = 'NA';
        }

        return [
            'TIN' => $tin,
            'legalName' => $customer->name,
            'identificationNumber' => $identificationNumber,
            'identificationScheme' => $identificationScheme,
            'telephone' => $this->formatPhoneNumber($customer->phone),
            'address' => [
                'addressLines' => [$customer->address ?: 'No address provided'],
                'cityName' => $customer->city ?: 'Kuala Lumpur',
                'postalZone' => $customer->postal_code ?: '50480',
                'countrySubentityCode' => $customer->state_code ?: '14',
                'countryCode' => $customer->country ?: 'MYS',
            ],
        ];
    }

    /**
     * Prepare customer info from custom array (for API submissions)
     */
    protected function prepareCustomCustomerInfo(array $customerInfo): array
    {
        // Determine TIN: Use provided TIN if available, otherwise use default
        $tin = $customerInfo['tin'] ?? 'EI00000000010';

        // Determine identification scheme: BRN takes priority, then NRIC
        if (! empty($customerInfo['brn'])) {
            $identificationScheme = 'BRN';
            $identificationNumber = $customerInfo['brn'];
        } elseif (! empty($customerInfo['nric'])) {
            $identificationScheme = 'NRIC';
            $identificationNumber = $customerInfo['nric'];
        } else {
            // If neither BRN nor NRIC provided, use default
            $identificationScheme = 'BRN';
            $identificationNumber = 'NA';
        }

        return [
            'TIN' => $tin,
            'legalName' => $customerInfo['name'] ?? 'Walk-in Customer',
            'identificationNumber' => $identificationNumber,
            'identificationScheme' => $identificationScheme,
            'telephone' => $this->formatPhoneNumber($customerInfo['phone'] ?? null),
            'address' => [
                'addressLines' => [$customerInfo['address'] ?? 'No address provided'],
                'cityName' => $customerInfo['city'] ?? 'Kuala Lumpur',
                'postalZone' => $customerInfo['postal_code'] ?? '50480',
                'countrySubentityCode' => $customerInfo['state_code'] ?? '14',
                'countryCode' => $customerInfo['country'] ?? 'MYS',
            ],
        ];
    }

    protected function prepareInvoiceLines(Order $order, ?array $customCustomerInfo = null)
    {
        // Determine the customer TIN
        $customerTin = null;
        if ($customCustomerInfo) {
            $customerTin = $customCustomerInfo['tin'] ?? 'EI00000000010';
        } else {
            $customer = $order->customer;
            if ($customer) {
                $customerTin = $customer->tin ?: 'EI00000000010';
            } else {
                $customerTin = 'EI00000000010';
            }
        }

        // Set item code based on TIN: "004" for EI00000000010, otherwise "022"
        $itemCode = ($customerTin === 'EI00000000010') ? '004' : '022';

        return $order->items->map(function ($item, $index) use ($itemCode) {
            $taxAmount = (float) ($item->price * $item->quantity) * ($this->settings->tax_percentage / 100);
            $subtotal = (float) ($item->price * $item->quantity);

            return [
                'id' => (string) ($index + 1),
                'quantity' => (int) $item->quantity,
                'unitPrice' => (float) $item->price,
                'unitCode' => 'XUN',
                'subtotal' => $subtotal,
                'itemDescription' => $item->product_name,
                'itemCommodityClassification' => [
                    'code' => $itemCode,
                    'listID' => 'CLASS',
                ],
                'lineTaxTotal' => [
                    'taxAmount' => $taxAmount,
                    'taxSubtotals' => [
                        [
                            'taxableAmount' => $subtotal,
                            'taxAmount' => $taxAmount,
                            'taxCategoryCode' => '01',
                            'percent' => (float) $this->settings->tax_percentage,
                        ],
                    ],
                ],
            ];
        })->toArray();
    }

    protected function prepareTaxTotal(Order $order)
    {
        return [
            'totalTaxAmount' => (float) $order->tax,
            'taxSubtotals' => [
                [
                    'taxableAmount' => (float) $order->subtotal,
                    'taxAmount' => (float) $order->tax,
                    'taxCategoryCode' => '01',
                    'percent' => (float) $this->settings->tax_percentage,
                ],
            ],
        ];
    }

    protected function prepareMonetaryTotal(Order $order)
    {
        return [
            'lineExtensionAmount' => (float) $order->subtotal,
            'taxExclusiveAmount' => (float) $order->subtotal,
            'taxInclusiveAmount' => (float) $order->total,
            'payableAmount' => (float) $order->total,
        ];
    }
}
