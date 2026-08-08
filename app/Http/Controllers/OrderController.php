<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShopSettings;
use App\Services\ProductSerialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['customer', 'user', 'items.product', 'myInvoisInvoice'])
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(request('remark'), function ($query, $remark) {
                $query->whereHas('items', function ($q) use ($remark) {
                    $q->where('remark', 'like', "%{$remark}%");
                });
            })
            ->when(request('start_date'), function ($query, $startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            })
            ->when(request('end_date'), function ($query, $endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            })
            ->when(request('product_search'), function ($query, $productSearch) {
                $query->whereHas('items.product', function ($q) use ($productSearch) {
                    $q->where('name', 'like', "%{$productSearch}%");
                });
            })
            ->when(request('sort_column'), function ($query, $column) {
                $direction = request('sort_direction', 'asc');

                // Map frontend column names to database column names
                $columnMap = [
                    'id' => 'orders.id',
                    'customer_name' => 'customers.name',
                    'total_amount' => 'orders.total',
                    'due' => 'orders.due_amount',
                    'items_count' => 'orders.id',
                    'payment_method' => 'orders.payment_method',
                    'delivery_method' => 'orders.delivery_method',
                    'status' => 'orders.status',
                    'created_at' => 'orders.created_at',
                ];

                $dbColumn = $columnMap[$column] ?? $column;

                if ($dbColumn === 'customers.name') {
                    $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
                        ->select('orders.*')
                        ->orderBy('customers.name', $direction)
                        ->orderBy('orders.id', $direction);
                } elseif ($dbColumn === 'items_count') {
                    $query->withCount('items')
                        ->orderBy('items_count', $direction);
                } else {
                    $query->orderBy($dbColumn, $direction);
                }
            }, function ($query) {
                $query->latest('orders.created_at');
            })
            ->paginate(10)
            ->through(function ($order) {
                return [
                    'id' => $order->id,
                    'customer_name' => $order->customer ? $order->customer->name : 'Walk-in Customer',
                    'subtotal' => number_format($order->subtotal, 2),
                    'tax' => number_format($order->tax, 2),
                    'total_amount' => number_format($order->total, 2),
                    'profit' => number_format($order->profit, 2),
                    'due' => number_format($order->due_amount, 2),
                    'payment_method' => ucfirst($order->payment_method),
                    'payment_status' => $order->paid_amount >= $order->total ? 'paid' :
                        ($order->paid_amount > 0 ? 'partial' : 'pending'),
                    'delivery_method' => $order->delivery_method,
                    'status' => $order->status,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'items_count' => $order->items->count(),
                    'deletion_reason' => $order->deletion_reason,
                    'myinvois_invoice' => $order->myInvoisInvoice ? true : false,
                ];
            })
            ->withQueryString();

        $settings = ShopSettings::first();
        $taxPercentage = $settings ? $settings->tax_percentage : 0;

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => request()->only(['search', 'remark', 'start_date', 'end_date', 'product_search']),
            'tax_percentage' => $taxPercentage,
        ]);
    }

    public function mySales()
    {
        $orders = Order::with(['customer', 'user', 'items.product'])
            ->where('user_id', auth()->id())
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->through(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer ? $order->customer->name : 'Walk-in Customer',
                    'total_amount' => number_format($order->total, 2),
                    'payment_method' => ucfirst($order->payment_method),
                    'payment_status' => $order->paid_amount >= $order->total ? 'paid' :
                        ($order->paid_amount > 0 ? 'partial' : 'pending'),
                    'status' => $order->status,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'items_count' => $order->items->count(),
                ];
            })
            ->withQueryString();

        return Inertia::render('Orders/MySales', [
            'orders' => $orders,
            'filters' => request()->only(['search']),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'user', 'items.product', 'myInvoisQueue', 'myInvoisInvoice', 'myInvoisInvoices', 'myInvoisCreditNotes']);

        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
                'address' => $order->customer->address,
            ] : null,
            'cashier' => [
                'id' => $order->user->id,
                'name' => $order->user->name,
            ],
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'price' => number_format($item->price, 2),
                    'total' => number_format($item->total, 2),
                    'profit' => number_format($item->profit, 2),
                    'remark' => $item->remark,
                ];
            }),
            'subtotal' => number_format($order->subtotal, 2),
            'tax' => number_format($order->tax, 2),
            'delivery_cost' => number_format($order->delivery_cost, 2),
            'discount' => number_format($order->discount, 2),
            'total' => number_format($order->total, 2),
            'profit' => number_format($order->profit, 2),
            'paid_amount' => number_format($order->paid_amount, 2),
            'due_amount' => number_format($order->due_amount, 2),
            'change_amount' => number_format($order->change_amount, 2),
            'payment_method' => ucfirst($order->payment_method),
            'delivery_method' => ucfirst($order->delivery_method),
            'remarks' => $order->remarks,
            'status' => $order->status,
            'payment_status' => $order->paid_amount >= $order->total ? 'paid' :
                ($order->paid_amount > 0 ? 'partial' : 'pending'),
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'myinvois_queue_status' => $order->myInvoisQueue ? $order->myInvoisQueue->status : null,
            'myinvois_id' => $order->myInvoisQueue ? $order->myInvoisQueue->myinvois_id : null,
            'myinvois_invoice' => $order->myInvoisInvoice ? [
                'uuid' => $order->myInvoisInvoice->uuid,
                'invoice_code_number' => $order->myInvoisInvoice->invoice_code_number,
                'submitted_at' => $order->myInvoisInvoice->created_at->format('Y-m-d H:i:s'),
                'within_cancellation_window' => app(\App\Services\MyInvoisService::class)->isWithinCancellationWindow($order->myInvoisInvoice),
                'window_expires_at' => $order->myInvoisInvoice->created_at
                    ->copy()
                    ->addHours((int) config('services.myinvois.cancellation_window_hours', 72))
                    ->format('Y-m-d H:i'),
            ] : null,
            // Full audit trail of every e-invoice + credit note for this order,
            // mirroring what the LHDN portal shows. Kept rows (credited/cancelled)
            // stay viewable here even though myinvois_invoice (active) hides them.
            'einvoice_history' => $order->myInvoisInvoices
                ->map(fn ($inv) => [
                    'id' => $inv->id,
                    'type' => 'Invoice',
                    'code' => $inv->invoice_code_number,
                    'uuid' => $inv->uuid,
                    'status' => $inv->status,
                    'submitted_at' => $inv->created_at?->format('Y-m-d H:i'),
                    'view_url' => route('orders.eInvoice', $order->id).'?document='.$inv->id,
                ])
                ->concat($order->myInvoisCreditNotes->map(fn ($cn) => [
                    'id' => $cn->id,
                    'type' => 'Credit Note',
                    'code' => $cn->credit_note_code_number,
                    'uuid' => $cn->uuid,
                    'status' => 'valid',
                    'reason' => $cn->reason,
                    'submitted_at' => $cn->created_at?->format('Y-m-d H:i'),
                    'view_url' => null,
                ]))
                ->sortByDesc('submitted_at')
                ->values()
                ->all(),
        ];

        return Inertia::render('Orders/Show', [
            'order' => $orderData,
            'myinvoisQueueDelayHours' => config('services.myinvois.queue_delay_hours', 72),
        ]);
    }

    public function eInvoice(Order $order)
    {
        $order->load(['customer', 'user', 'items.product', 'myInvoisInvoice']);

        // View a specific document when requested (history panel), otherwise the
        // active e-invoice, falling back to the latest of any status so a
        // credited/cancelled e-invoice stays viewable.
        $documentId = request('document');
        $myInvoisInvoice = $documentId
            ? $order->myInvoisInvoices()->whereKey($documentId)->first()
            : ($order->myInvoisInvoice ?? $order->latestMyInvoisInvoice);
        $qrCodeUrl = null;
        $documentDetails = null;

        if ($myInvoisInvoice) {
            $myInvoisService = app(\App\Services\MyInvoisService::class);
            $documentDetails = $myInvoisService->getDocumentDetails($myInvoisInvoice->uuid);

            // Try to get longId from API response first
            $longId = null;
            if ($documentDetails && isset($documentDetails['longId'])) {
                $longId = $documentDetails['longId'];
            } elseif ($myInvoisInvoice->response_payload && isset($myInvoisInvoice->response_payload['longId'])) {
                // Fallback to stored response payload
                $longId = $myInvoisInvoice->response_payload['longId'];
            }

            if ($longId && $myInvoisInvoice->uuid) {
                $qrCodeUrl = $myInvoisService->generateQrCodeUrl(
                    $myInvoisInvoice->uuid,
                    $longId
                );
            }
        }

        $settings = \App\Models\ShopSettings::first();

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
            'myinvois_invoice' => $myInvoisInvoice ? [
                'uuid' => $myInvoisInvoice->uuid,
                'invoice_code_number' => $myInvoisInvoice->invoice_code_number,
                'submission_uid' => $myInvoisInvoice->submission_uid,
                'created_at' => $myInvoisInvoice->created_at,
            ] : null,
            'qr_code_url' => $qrCodeUrl,
            'document_details' => $documentDetails,
        ];

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
        ];

        return Inertia::render('Orders/EInvoice', [
            'order' => $orderData,
            'shopSettings' => $shopSettings,
        ]);
    }

    public function eInvoicePdf(Order $order)
    {
        $order->load(['customer', 'user', 'items.product', 'myInvoisInvoice']);

        // View a specific document when requested (history panel), otherwise the
        // active e-invoice, falling back to the latest of any status so a
        // credited/cancelled e-invoice stays viewable.
        $documentId = request('document');
        $myInvoisInvoice = $documentId
            ? $order->myInvoisInvoices()->whereKey($documentId)->first()
            : ($order->myInvoisInvoice ?? $order->latestMyInvoisInvoice);
        $qrCodeUrl = null;
        $documentDetails = null;

        if ($myInvoisInvoice) {
            $myInvoisService = app(\App\Services\MyInvoisService::class);
            $documentDetails = $myInvoisService->getDocumentDetails($myInvoisInvoice->uuid);

            // Try to get longId from API response first
            $longId = null;
            if ($documentDetails && isset($documentDetails['longId'])) {
                $longId = $documentDetails['longId'];
            } elseif ($myInvoisInvoice->response_payload && isset($myInvoisInvoice->response_payload['longId'])) {
                // Fallback to stored response payload
                $longId = $myInvoisInvoice->response_payload['longId'];
            }

            if ($longId && $myInvoisInvoice->uuid) {
                $qrCodeUrl = $myInvoisService->generateQrCodeUrl(
                    $myInvoisInvoice->uuid,
                    $longId
                );
            }
        }

        $settings = ShopSettings::first();

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
            'myinvois_invoice' => $myInvoisInvoice ? [
                'uuid' => $myInvoisInvoice->uuid,
                'invoice_code_number' => $myInvoisInvoice->invoice_code_number,
                'submission_uid' => $myInvoisInvoice->submission_uid,
                'created_at' => $myInvoisInvoice->created_at,
            ] : null,
            'qr_code_url' => $qrCodeUrl,
            'qr_code_base64' => null,
        ];

        // Generate QR code as base64 for PDF
        if ($qrCodeUrl) {
            try {
                $qrCodeImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data='.urlencode($qrCodeUrl);
                $qrCodeImage = file_get_contents($qrCodeImageUrl);
                if ($qrCodeImage) {
                    $orderData['qr_code_base64'] = 'data:image/png;base64,'.base64_encode($qrCodeImage);
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

        $pdf = Pdf::loadView('pdf.e-invoice', [
            'order' => $orderData,
            'shopSettings' => $shopSettings,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("e-invoice-{$order->id}.pdf");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.remark' => 'nullable|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'string',
            'customer_id' => 'nullable|exists:customers,id',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'delivery_cost' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'due_amount' => 'required|numeric|min:0',
            'change_amount' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,e-wallet,online_transfer',
            'delivery_method' => 'required|in:pickup,delivery,walk-in,shopee,tiktok,lazada',
            'remarks' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Calculate total profit and subtotal
            $totalProfit = 0;
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['id']);
                $quantity = $product->serial_tracked
                    ? count(array_unique(array_map('trim', $item['serials'] ?? [])))
                    : $item['quantity'];
                $itemProfit = ($item['price'] - $product->cost_price) * $quantity;
                $totalProfit += $itemProfit;
                $subtotal += $item['price'] * $quantity;
            }

            // Adjust profit based on discount
            if ($validated['discount'] > 0) {
                $totalProfit -= $validated['discount'];
            }

            // Create the order
            $order = Order::create([
                'customer_id' => $validated['customer_id'] ?? null,
                'user_id' => auth()->id(),
                'subtotal' => $validated['subtotal'],
                'tax' => $validated['tax'],
                'delivery_cost' => $validated['delivery_cost'],
                'total' => $validated['total'],
                'paid_amount' => $validated['paid_amount'],
                'due_amount' => $validated['due_amount'],
                'change_amount' => $validated['change_amount'],
                'payment_method' => $validated['payment_method'],
                'delivery_method' => $validated['delivery_method'],
                'remarks' => $validated['remarks'] ?? null,
                'discount' => $validated['discount'],
                'status' => 'pending',
                'profit' => $totalProfit,
            ]);

            // Create order items and update product stock
            $service = app(ProductSerialService::class);

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['id']);

                if ($product->serial_tracked) {
                    $serials = array_values(array_unique(array_map('trim', $item['serials'] ?? [])));
                    $quantity = count($serials);

                    if ($quantity < 1) {
                        throw new \Exception("No serial numbers selected for product: {$product->name}");
                    }

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['id'],
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'price' => $item['price'],
                        'cost_price' => $product->cost_price,
                        'total' => $item['price'] * $quantity,
                        'profit' => ($item['price'] - $product->cost_price) * $quantity,
                        'remark' => $item['remark'] ?? null,
                    ]);

                    $service->allocate($orderItem, $product, $serials);

                    continue;
                }

                // Untracked product — existing aggregate-stock path
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'cost_price' => $product->cost_price,
                    'total' => $item['price'] * $item['quantity'],
                    'profit' => ($item['price'] - $product->cost_price) * $item['quantity'],
                    'remark' => $item['remark'] ?? null,
                ]);

                $product->decrement('stock', $item['quantity']);
            }

            // Queue invoice for MyInvois (configurable delay) - only for walk-in orders
            try {
                $myInvoisService = app(\App\Services\MyInvoisService::class);
                if ($myInvoisService->isEnabled() && $validated['delivery_method'] === 'walk-in') {
                    $myInvoisService->queueInvoice($order);
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to queue MyInvois invoice', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order' => $order->load('items'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function edit(Order $order)
    {
        $order->load(['customer', 'user', 'items.product', 'items.serials']);

        return Inertia::render('Orders/Edit', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone,
                    'address' => $order->customer->address,
                ] : null,
                'cashier' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                ],
                'items' => $order->items->map(function ($item) {
                    $serialTracked = $item->product && $item->product->serial_tracked;

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'serial_tracked' => $serialTracked,
                        'serials' => $serialTracked
                            ? $item->serials->pluck('serial_number')->values()->all()
                            : [],
                        'quantity' => $item->quantity,
                        'price' => number_format($item->price, 2),
                        'total' => number_format($item->total, 2),
                        'remark' => $item->remark,
                    ];
                }),
                'subtotal' => number_format($order->subtotal, 2),
                'tax' => number_format($order->tax, 2),
                'delivery_cost' => number_format($order->delivery_cost, 2),
                'discount' => number_format($order->discount, 2),
                'total' => number_format($order->total, 2),
                'paid_amount' => number_format($order->paid_amount, 2),
                'due_amount' => number_format($order->due_amount, 2),
                'change_amount' => number_format($order->change_amount, 2),
                'payment_method' => $order->payment_method,
                'delivery_method' => $order->delivery_method,
                'remarks' => $order->remarks,
                'status' => $order->status,
                'payment_status' => $order->paid_amount >= $order->total ? 'paid' :
                    ($order->paid_amount > 0 ? 'partial' : 'pending'),
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            ],
            'customers' => \App\Models\Customer::select('id', 'name', 'email', 'phone', 'address')->get(),
            'products' => \App\Models\Product::select('id', 'name', 'price', 'stock', 'serial_tracked')
                ->where('stock', '>', 0)
                ->orWhereHas('serials', fn ($q) => $q->where('status', 'sold')->whereHas('order', fn ($o) => $o->where('id', $order->id)))
                ->get(),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.remark' => 'nullable|string',
            'items.*.serials' => 'nullable|array',
            'items.*.serials.*' => 'string',
            'payment_method' => 'required|in:cash,card,e-wallet,online_transfer',
            'delivery_method' => 'required|in:pickup,delivery,walk-in,shopee,tiktok,lazada',
            'delivery_cost' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'due_amount' => 'required|numeric|min:0',
            'change_amount' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
            'discount' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Calculate total profit
            $totalProfit = 0;
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                if ($product) {
                    $quantity = $product->serial_tracked
                        ? count(array_unique(array_map('trim', $item['serials'] ?? [])))
                        : $item['quantity'];
                    $itemProfit = ($item['price'] - $product->cost_price) * $quantity;
                    $totalProfit += $itemProfit;
                }
            }

            // Adjust profit based on discount
            if ($validated['discount'] > 0) {
                $totalProfit -= $validated['discount'];
            }

            // Check if delivery_method changed
            $oldDeliveryMethod = $order->delivery_method;
            $newDeliveryMethod = $validated['delivery_method'];
            $deliveryMethodChanged = $oldDeliveryMethod !== $newDeliveryMethod;

            // Update order details
            $order->update([
                'customer_id' => $validated['customer_id'] ?? null,
                'payment_method' => $validated['payment_method'],
                'delivery_method' => $validated['delivery_method'],
                'delivery_cost' => $validated['delivery_cost'],
                'paid_amount' => $validated['paid_amount'],
                'due_amount' => $validated['due_amount'],
                'change_amount' => $validated['change_amount'],
                'remarks' => $validated['remarks'] ?? null,
                'discount' => $validated['discount'],
                'subtotal' => collect($validated['items'])->sum('total'),
                'tax' => collect($validated['items'])->sum('total') * (settings('tax_percentage', 0) / 100),
                'total' => collect($validated['items'])->sum('total') +
                          (collect($validated['items'])->sum('total') * (settings('tax_percentage', 0) / 100)) +
                          $validated['delivery_cost'] -
                          $validated['discount'],
                'profit' => $totalProfit,
            ]);

            // Handle MyInvois queue based on delivery_method change
            if ($deliveryMethodChanged) {
                $myInvoisService = app(\App\Services\MyInvoisService::class);
                $queueItem = $order->myInvoisQueue;

                if ($newDeliveryMethod !== 'walk-in') {
                    // If changed to non-walk-in, remove from queue if exists
                    if ($queueItem && $queueItem->status === 'pending') {
                        $queueItem->delete();
                        \Log::info('Removed order from MyInvois queue - delivery method changed to non-walk-in', [
                            'order_id' => $order->id,
                            'old_delivery_method' => $oldDeliveryMethod,
                            'new_delivery_method' => $newDeliveryMethod,
                        ]);
                    }
                } else {
                    // If changed to walk-in, ensure it's in queue
                    if (! $queueItem && $myInvoisService->isEnabled()) {
                        try {
                            $myInvoisService->queueInvoice($order);
                            \Log::info('Added order to MyInvois queue - delivery method changed to walk-in', [
                                'order_id' => $order->id,
                                'old_delivery_method' => $oldDeliveryMethod,
                                'new_delivery_method' => $newDeliveryMethod,
                            ]);
                        } catch (\Throwable $e) {
                            \Log::error('Failed to queue MyInvois invoice after delivery method change', [
                                'order_id' => $order->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // Update order items
            // Release this order's serials back to the pool, and restore
            // aggregate stock for untracked items, before deleting items.
            $service = app(ProductSerialService::class);
            $service->release($order->id);
            foreach ($order->items as $oldItem) {
                if ($oldItem->product && ! $oldItem->product->serial_tracked) {
                    $oldItem->product->increment('stock', $oldItem->quantity);
                }
            }
            $order->items()->delete(); // Remove existing items

            foreach ($validated['items'] as $item) {
                $product = $item['product_id'] ? Product::find($item['product_id']) : null;
                $costPrice = $product ? $product->cost_price : 0;

                if ($product && $product->serial_tracked) {
                    $serials = array_values(array_unique(array_map('trim', $item['serials'] ?? [])));
                    $quantity = count($serials);

                    if ($quantity < 1) {
                        throw new \Exception("No serial numbers selected for product: {$product->name}");
                    }

                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => $quantity,
                        'price' => $item['price'],
                        'cost_price' => $costPrice,
                        'total' => $item['price'] * $quantity,
                        'profit' => ($item['price'] - $costPrice) * $quantity,
                        'remark' => $item['remark'] ?? null,
                    ]);

                    $service->allocate($orderItem, $product, $serials);

                    continue;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'cost_price' => $costPrice,
                    'total' => $item['total'],
                    'profit' => ($item['price'] - $costPrice) * $item['quantity'],
                    'remark' => $item['remark'] ?? null,
                ]);

                if ($product) {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            DB::commit();

            return back()->with('success', 'Order updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update order: '.$e->getMessage());
        }
    }

    public function create()
    {
        $settings = ShopSettings::first();
        $taxPercentage = $settings ? $settings->tax_percentage : 0;

        return Inertia::render('Orders/Create', [
            'tax_percentage' => $taxPercentage,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled',
        ]);

        if ($validated['status'] === 'cancelled' && $order->status !== 'cancelled') {
            app(ProductSerialService::class)->release($order->id);
            foreach ($order->items as $item) {
                if ($item->product && ! $item->product->serial_tracked) {
                    $item->product->increment('stock', $item->quantity);
                }
            }
        }

        $order->update(['status' => $validated['status'], 'payment_status' => $validated['status']]);

        return back()->with('success', 'Order status updated successfully');
    }

    public function destroy(Request $request, Order $order)
    {
        $validated = $request->validate([
            'deletion_reason' => 'required|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Check if order is older than configured delay hours and has been pushed to MyInvois
            $delayHours = config('services.myinvois.queue_delay_hours', 72);
            $orderAge = $order->created_at->diffInHours(now());
            $queueItem = $order->myInvoisQueue;

            if ($orderAge >= $delayHours && $queueItem && $queueItem->status === 'pushed' && $queueItem->myinvois_id) {
                // Order is pushed to MyInvois, need to cancel it first
                try {
                    $myInvoisService = app(\App\Services\MyInvoisService::class);
                    if ($myInvoisService->isEnabled()) {
                        $myInvoisService->cancelInvoice($queueItem->myinvois_id, $validated['deletion_reason']);

                        // Update queue status
                        $queueItem->update(['status' => 'cancelled']);
                    }

                    // Mark order as cancelled instead of deleting
                    $order->update([
                        'status' => 'cancelled',
                        'deletion_reason' => $validated['deletion_reason'],
                    ]);

                    app(ProductSerialService::class)->release($order->id);
                    foreach ($order->items as $item) {
                        if ($item->product && ! $item->product->serial_tracked) {
                            $item->product->increment('stock', $item->quantity);
                        }
                    }

                    \Log::info('Order cancelled on MyInvois', [
                        'order_id' => $order->id,
                        'myinvois_id' => $queueItem->myinvois_id,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('Failed to cancel MyInvois invoice', ['error' => $e->getMessage()]);
                    throw $e;
                }
            } else {
                // Order can be deleted right away (not yet pushed or less than 72 hours old)
                if ($queueItem) {
                    $queueItem->update(['status' => 'cancelled']);
                }

                // Save deletion reason before deleting
                $order->update([
                    'deletion_reason' => $validated['deletion_reason'],
                ]);

                // Restore product stock (untracked) and release serials (tracked)
                app(ProductSerialService::class)->release($order->id);
                foreach ($order->items as $item) {
                    if ($item->product && ! $item->product->serial_tracked) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }

                // Delete the order (this will also delete order items due to cascade)
                $order->delete();
            }

            DB::commit();

            return back()->with('success', 'Order deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete order: '.$e->getMessage());
        }
    }

    public function pushToMyInvois(Order $order)
    {
        // A push is a reissue when the order already has prior e-invoice
        // submissions (cancelled/credited rows kept for audit). Reissuing a
        // credited e-invoice is an admin-only action — enforced here (not just
        // in the UI) so the gate holds even for direct requests. Checked before
        // the try block so the 403 is not swallowed by the catch.
        $isReissue = $order->myInvoisInvoices()->exists();

        if ($isReissue && ! auth()->user()?->hasRole('admin')) {
            abort(403, 'Only administrators can reissue an e-invoice.');
        }

        try {
            $myInvoisService = app(\App\Services\MyInvoisService::class);

            if (! $myInvoisService->isEnabled()) {
                return back()->with('error', 'MyInvois service is not enabled');
            }

            // Reissues auto-email the corrected e-invoice; the first push does not.

            // Force refresh to use latest order/customer data instead of old queue payload
            $result = $myInvoisService->submitInvoice($order, forceRefresh: true);

            if ($result) {
                $message = 'Invoice pushed to MyInvois successfully';

                if ($isReissue) {
                    // The credit note had cancelled the order; a successful reissue
                    // revives it with the new valid e-invoice.
                    $order->update(['status' => 'completed']);

                    $emailSent = $this->sendEInvoiceEmail($order);
                    $message .= $emailSent
                        ? ' and the reissued e-invoice was emailed to the customer.'
                        : ' (reissued e-invoice was not emailed — no customer email on file or send failed; check logs).';
                }

                return back()->with('success', $message);
            } else {
                return back()->with('error', 'Failed to push invoice to MyInvois. Check logs for details.');
            }
        } catch (\Exception $e) {
            \Log::error('Push to MyInvois failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to push invoice: '.$e->getMessage());
        }
    }

    public function clearFromQueue(Order $order)
    {
        try {
            $queueItem = $order->myInvoisQueue;

            if (! $queueItem) {
                return back()->with('error', 'No queue item found for this order');
            }

            if ($queueItem->status === 'pushed') {
                return back()->with('error', 'Cannot clear invoice that has already been pushed');
            }

            $queueItem->update(['status' => 'cancelled']);

            return back()->with('success', 'Invoice cleared from MyInvois queue');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to clear invoice: '.$e->getMessage());
        }
    }

    public function addToQueue(Order $order)
    {
        try {
            // Only allow walk-in orders to be added to queue
            if ($order->delivery_method !== 'walk-in') {
                return back()->with('error', 'Only walk-in orders can be added to MyInvois queue');
            }

            // Check if already in queue
            if ($order->myInvoisQueue) {
                return back()->with('error', 'Order is already in the MyInvois queue');
            }

            // Check if already pushed to MyInvois
            if ($order->myInvoisInvoice) {
                return back()->with('error', 'Order has already been pushed to MyInvois');
            }

            $myInvoisService = app(\App\Services\MyInvoisService::class);

            if (! $myInvoisService->isEnabled()) {
                return back()->with('error', 'MyInvois service is not enabled');
            }

            $myInvoisService->queueInvoice($order);

            return back()->with('success', 'Invoice added to consolidation queue successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to add invoice to queue', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to add invoice to queue: '.$e->getMessage());
        }
    }

    public function cancelMyInvoisInvoice(Request $request, Order $order)
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:1000',
            ]);

            $myInvoisInvoice = $order->myInvoisInvoice;

            if (! $myInvoisInvoice) {
                return back()->with('error', 'No MyInvois invoice found for this order');
            }

            $myInvoisService = app(\App\Services\MyInvoisService::class);

            if (! $myInvoisService->isEnabled()) {
                return back()->with('error', 'MyInvois service is not enabled');
            }

            if (! $myInvoisService->isWithinCancellationWindow($myInvoisInvoice)) {
                return back()->with('error', 'The LHDN 72-hour cancellation window has lapsed. Use "Issue Credit Note & Reissue" instead.');
            }

            $result = $myInvoisService->cancelInvoice($myInvoisInvoice->uuid, $validated['reason']);

            if ($result) {
                // Keep the row for audit; the active-scoped relation hides it
                $myInvoisInvoice->update(['status' => 'cancelled']);
                $order->myInvoisQueue()->delete();

                // Set order status to cancelled
                $order->update(['status' => 'cancelled']);

                return back()->with('success', 'MyInvois invoice cancelled successfully and order status updated to cancelled. You can now edit the order and resubmit.');
            } else {
                return back()->with('error', 'Failed to cancel MyInvois invoice. Check logs for details.');
            }
        } catch (\Exception $e) {
            \Log::error('Cancel MyInvois invoice failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to cancel invoice: '.$e->getMessage());
        }
    }

    public function creditNoteMyInvois(Request $request, Order $order)
    {
        // Issuing a credit note is an admin-only action — enforced here (not just
        // in the UI) so it holds for direct requests too.
        if (! auth()->user()?->hasRole('admin')) {
            abort(403, 'Only administrators can issue a credit note.');
        }

        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:1000',
            ]);

            if (! $order->myInvoisInvoice) {
                return back()->with('error', 'No active MyInvois invoice found for this order');
            }

            $myInvoisService = app(\App\Services\MyInvoisService::class);

            if (! $myInvoisService->isEnabled()) {
                return back()->with('error', 'MyInvois service is not enabled');
            }

            $result = $myInvoisService->submitCreditNote($order, $validated['reason']);

            if ($result) {
                $order->myInvoisQueue()->delete();
                $order->update(['status' => 'cancelled']);

                return back()->with('success', 'Credit note submitted to MyInvois. The original e-invoice has been reversed — you can now edit the order and push a corrected invoice to MyInvois.');
            }

            return back()->with('error', 'Failed to submit credit note to MyInvois. Check logs for details.');
        } catch (\Exception $e) {
            \Log::error('Credit note MyInvois failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to submit credit note: '.$e->getMessage());
        }
    }

    public function exportCsv()
    {
        $orders = \App\Models\Order::with(['customer', 'user', 'items.product'])
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(request('remark'), function ($query, $remark) {
                $query->whereHas('items', function ($q) use ($remark) {
                    $q->where('remark', 'like', "%{$remark}%");
                });
            })
            ->when(request('start_date'), function ($query, $startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            })
            ->when(request('end_date'), function ($query, $endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            })
            ->when(request('product_search'), function ($query, $productSearch) {
                $query->whereHas('items.product', function ($q) use ($productSearch) {
                    $q->where('name', 'like', "%{$productSearch}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders.csv"',
        ];

        $columns = [
            'id', 'order_number', 'customer_name', 'cashier_name', 'subtotal', 'tax', 'total', 'profit', 'paid_amount', 'due_amount', 'change_amount', 'payment_method', 'delivery_method', 'status', 'created_at', 'item_remarks',
        ];

        $callback = function () use ($orders, $columns) {
            $file = fopen('php://output', 'w');
            // Header
            fputcsv($file, $columns);
            // Rows
            foreach ($orders as $order) {
                $remarks = $order->items->pluck('remark')->filter()->values()->all();
                fputcsv($file, [
                    $order->id,
                    $order->order_number ?? '',
                    $order->customer ? $order->customer->name : 'Walk-in Customer',
                    $order->user ? $order->user->name : '',
                    $order->subtotal,
                    $order->tax,
                    $order->total,
                    $order->profit,
                    $order->paid_amount,
                    $order->due_amount,
                    $order->change_amount,
                    $order->payment_method,
                    $order->delivery_method,
                    $order->status,
                    $order->created_at,
                    json_encode($remarks),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function apiSubmitMyInvois(Request $request, $orderId)
    {
        try {
            // Find the order - include soft-deleted orders for API access
            $order = Order::withTrashed()->findOrFail($orderId);

            // Ensure order has an ID
            if (! $order->id) {
                \Log::error('Order ID is null after findOrFail', [
                    'order_id_param' => $orderId,
                    'order' => $order->toArray(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid order: Order ID is missing',
                ], 422);
            }

            \Log::info('API Submit MyInvois - Order loaded', [
                'order_id' => $order->id,
                'order_exists' => $order->exists,
                'is_soft_deleted' => $order->trashed(),
            ]);

            // Check if invoice has already been submitted to MyInvois
            $order->load('myInvoisInvoice');
            if ($order->myInvoisInvoice) {
                \Log::warning('API Submit MyInvois - Invoice already submitted', [
                    'order_id' => $order->id,
                    'myinvois_uuid' => $order->myInvoisInvoice->uuid,
                    'invoice_code_number' => $order->myInvoisInvoice->invoice_code_number,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invoice has already been submitted to MyInvois',
                    'error_code' => 'ALREADY_SUBMITTED',
                    'myinvois_invoice' => [
                        'uuid' => $order->myInvoisInvoice->uuid,
                        'invoice_code_number' => $order->myInvoisInvoice->invoice_code_number,
                        'submission_uid' => $order->myInvoisInvoice->submission_uid,
                        'submitted_at' => $order->myInvoisInvoice->created_at->toIso8601String(),
                    ],
                ], 409); // 409 Conflict is appropriate for "already exists" scenarios
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:10',
                'state_code' => 'nullable|string|max:2',
                'country' => 'nullable|string|max:3',
                'tin' => 'nullable|string|max:20',
                'brn' => 'nullable|string|max:50',
                'nric' => 'nullable|string|max:50',
            ]);

            // Set default values for optional fields
            $validated['country'] = $validated['country'] ?? 'MYS';

            // Find or create customer by phone number or email
            $customer = null;
            $customerWasCreated = false;
            $customerWasUpdated = false;

            if (! empty($validated['phone'])) {
                // Try to find customer by phone number first (normalize phone for comparison)
                $normalizedPhone = preg_replace('/[^0-9+]/', '', $validated['phone']);
                $customer = \App\Models\Customer::where(function ($query) use ($validated, $normalizedPhone) {
                    $query->where('phone', $validated['phone'])
                        ->orWhere('phone', $normalizedPhone)
                        ->orWhereRaw("REPLACE(REPLACE(phone, ' ', ''), '-', '') = ?", [preg_replace('/[^0-9+]/', '', $validated['phone'])]);
                })->first();
            }

            // If not found by phone (or phone not provided), try to find by email
            if (! $customer && ! empty($validated['email'])) {
                $customer = \App\Models\Customer::where('email', $validated['email'])->first();
            }

            if ($customer) {
                // Update existing customer with new information
                $customerWasUpdated = true;
                $customer->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? $customer->phone,
                    'address' => $validated['address'] ?? $customer->address,
                    'city' => $validated['city'] ?? $customer->city,
                    'postal_code' => $validated['postal_code'] ?? $customer->postal_code,
                    'state_code' => $validated['state_code'] ?? $customer->state_code,
                    'country' => $validated['country'] ?? $customer->country ?? 'MYS',
                    'tin' => $validated['tin'] ?? $customer->tin,
                    'brn' => $validated['brn'] ?? $customer->brn,
                    'nric' => $validated['nric'] ?? $customer->nric,
                    'status' => 'active', // Ensure customer is active
                ]);
                \Log::info('API Submit MyInvois - Customer updated', [
                    'customer_id' => $customer->id,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                ]);
            } else {
                // Create new customer
                $customerWasCreated = true;
                $customer = \App\Models\Customer::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                    'state_code' => $validated['state_code'] ?? null,
                    'country' => $validated['country'] ?? 'MYS',
                    'tin' => $validated['tin'] ?? null,
                    'brn' => $validated['brn'] ?? null,
                    'nric' => $validated['nric'] ?? null,
                    'status' => 'active',
                ]);
                \Log::info('API Submit MyInvois - Customer created', [
                    'customer_id' => $customer->id,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                ]);
            }

            // Assign order to customer if customer was found/created
            if ($customer) {
                $order->update(['customer_id' => $customer->id]);
                \Log::info('API Submit MyInvois - Order assigned to customer', [
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                ]);
            }

            $myInvoisService = app(\App\Services\MyInvoisService::class);

            if (! $myInvoisService->isEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'MyInvois service is not enabled',
                ], 400);
            }

            // Prepare custom customer info (use customer data if available, otherwise use validated data)
            $customCustomerInfo = [
                'name' => $customer ? $customer->name : $validated['name'],
                'phone' => $customer ? $customer->phone : ($validated['phone'] ?? null),
                'address' => $customer ? $customer->address : ($validated['address'] ?? null),
                'city' => $customer ? $customer->city : ($validated['city'] ?? null),
                'postal_code' => $customer ? $customer->postal_code : ($validated['postal_code'] ?? null),
                'state_code' => $customer ? $customer->state_code : ($validated['state_code'] ?? null),
                'country' => $customer ? $customer->country : ($validated['country'] ?? 'MYS'),
                'tin' => $customer ? $customer->tin : ($validated['tin'] ?? null),
                'brn' => $customer ? $customer->brn : ($validated['brn'] ?? null),
                'nric' => $customer ? $customer->nric : ($validated['nric'] ?? null),
            ];

            // Submit invoice with custom customer info
            $result = $myInvoisService->submitInvoice($order, forceRefresh: true, customCustomerInfo: $customCustomerInfo);

            if (! $result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to submit invoice to MyInvois. Check logs for details.',
                ], 500);
            }

            // Send e-invoice PDF via email
            $emailSent = false;
            if (! empty($validated['email'])) {
                $emailSent = $this->sendEInvoiceEmail($order, $validated['email'], $validated['name'] ?? null);
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice submitted to MyInvois successfully'.($emailSent ? ' and e-invoice sent via email' : ''),
                'data' => [
                    'order_id' => $order->id,
                    'customer_id' => $customer ? $customer->id : null,
                    'customer_created' => $customerWasCreated,
                    'customer_updated' => $customerWasUpdated,
                    'email_sent' => $emailSent,
                    'email' => $validated['email'] ?? null,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('API Submit MyInvois failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit invoice: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function prepareOrderDataForPdf(Order $order, $myInvoisInvoice, $qrCodeUrl)
    {
        return [
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
            'myinvois_invoice' => $myInvoisInvoice ? [
                'uuid' => $myInvoisInvoice->uuid,
                'invoice_code_number' => $myInvoisInvoice->invoice_code_number,
                'submission_uid' => $myInvoisInvoice->submission_uid,
                'created_at' => $myInvoisInvoice->created_at,
            ] : null,
            'qr_code_url' => $qrCodeUrl,
        ];
    }

    protected function prepareShopSettingsForPdf($settings)
    {
        return [
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
    }

    /**
     * Generate the e-invoice PDF for the order's active e-invoice and email it.
     * Best-effort: returns false (and logs) on any failure or when no recipient
     * email is available, without throwing.
     */
    protected function sendEInvoiceEmail(Order $order, ?string $email = null, ?string $name = null): bool
    {
        $email = $email ?? $order->customer?->email;

        if (empty($email)) {
            return false;
        }

        try {
            $order->load(['customer', 'user', 'items.product', 'myInvoisInvoice']);

            $myInvoisService = app(\App\Services\MyInvoisService::class);
            $myInvoisInvoice = $order->myInvoisInvoice;
            $qrCodeUrl = null;

            if ($myInvoisInvoice) {
                $documentDetails = $myInvoisService->getDocumentDetails($myInvoisInvoice->uuid);

                $longId = null;
                if ($documentDetails && isset($documentDetails['longId'])) {
                    $longId = $documentDetails['longId'];
                } elseif ($myInvoisInvoice->response_payload && isset($myInvoisInvoice->response_payload['longId'])) {
                    $longId = $myInvoisInvoice->response_payload['longId'];
                }

                if ($longId && $myInvoisInvoice->uuid) {
                    $qrCodeUrl = $myInvoisService->generateQrCodeUrl($myInvoisInvoice->uuid, $longId);
                }
            }

            $settings = ShopSettings::first();
            $orderData = $this->prepareOrderDataForPdf($order, $myInvoisInvoice, $qrCodeUrl);
            $shopSettings = $this->prepareShopSettingsForPdf($settings);

            $qrCodeBase64 = null;
            if ($qrCodeUrl) {
                try {
                    $qrCodeImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data='.urlencode($qrCodeUrl);
                    $qrCodeImage = file_get_contents($qrCodeImageUrl);
                    if ($qrCodeImage) {
                        $qrCodeBase64 = 'data:image/png;base64,'.base64_encode($qrCodeImage);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to generate QR code for PDF', ['error' => $e->getMessage()]);
                }
            }
            $orderData['qr_code_base64'] = $qrCodeBase64;

            $pdf = Pdf::loadView('pdf.e-invoice', [
                'order' => $orderData,
                'shopSettings' => $shopSettings,
            ])->setPaper('a4', 'portrait');

            \Illuminate\Support\Facades\Mail::to($email)
                ->send(new \App\Mail\EInvoiceEmail($order, $pdf->output(), $name));

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send e-invoice email', [
                'order_id' => $order->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
