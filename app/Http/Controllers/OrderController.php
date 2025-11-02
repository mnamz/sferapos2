<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\ShopSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Services\TmsReceiptService;
use App\Services\ConsolidationQueueService;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['customer', 'user', 'items.product'])
            ->when(request('search'), function($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhereHas('customer', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when(request('remark'), function($query, $remark) {
                $query->whereHas('items', function($q) use ($remark) {
                    $q->where('remark', 'like', "%{$remark}%");
                });
            })
            ->when(request('start_date'), function($query, $startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            })
            ->when(request('end_date'), function($query, $endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            })
            ->when(request('product_search'), function($query, $productSearch) {
                $query->whereHas('items.product', function($q) use ($productSearch) {
                    $q->where('name', 'like', "%{$productSearch}%");
                });
            })
            ->when(request('sort_column'), function($query, $column) {
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
                    'created_at' => 'orders.created_at'
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
            }, function($query) {
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
            ->when(request('search'), function($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhereHas('customer', function($q) use ($search) {
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
        $order->load(['customer', 'user', 'items.product', 'expenses', 'myInvoisInvoice']);
        
        $queueService = new ConsolidationQueueService();
        
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
            'expenses' => $order->expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'name' => $expense->name,
                    'amount' => number_format($expense->amount, 2),
                    'remark' => $expense->remark,
                ];
            }),
            'paid_amount' => number_format($order->paid_amount, 2),
            'due_amount' => number_format($order->due_amount, 2),
            'change_amount' => number_format($order->change_amount, 2),
            'payment_method' => ucfirst($order->payment_method),
            'delivery_method' => ucfirst($order->delivery_method),
            'delivery_name' => $order->delivery_name,
            'delivery_company_name' => $order->delivery_company_name,
            'delivery_address' => $order->delivery_address,
            'delivery_phone' => $order->delivery_phone,
            'remarks' => $order->remarks,
            'status' => $order->status,
            'payment_status' => $order->paid_amount >= $order->total ? 'paid' : 
                ($order->paid_amount > 0 ? 'partial' : 'pending'),
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'my_invois_invoice' => $order->myInvoisInvoice ? [
                'id' => $order->myInvoisInvoice->id,
                'submission_uid' => $order->myInvoisInvoice->submission_uid,
                'uuid' => $order->myInvoisInvoice->uuid,
                'invoice_code_number' => $order->myInvoisInvoice->invoice_code_number,
            ] : null,
            'in_consolidation_queue' => $queueService->isInQueue($order->id),
        ];

        return Inertia::render('Orders/Show', [
            'order' => $orderData,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.remark' => 'nullable|string',
            'items.*.price' => 'required|numeric|min:0',
            'expenses' => 'nullable|array',
            'expenses.*.name' => 'required_with:expenses|string',
            'expenses.*.amount' => 'required_with:expenses|numeric|min:0',
            'expenses.*.remark' => 'nullable|string',
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
                $itemProfit = ($item['price'] - $product->cost_price) * $item['quantity'];
                $totalProfit += $itemProfit;
                $subtotal += $item['price'] * $item['quantity'];
            }

            // Adjust profit based on discount
            if ($validated['discount'] > 0) {
                $totalProfit -= $validated['discount'];
            }

            // Subtract expenses from profit
            $expensesTotal = 0;
            if (!empty($validated['expenses'])) {
                foreach ($validated['expenses'] as $expense) {
                    $expensesTotal += (float) $expense['amount'];
                }
            }
            $totalProfit -= $expensesTotal;

            // Create the order
            $order = Order::create([
                'customer_id' => $validated['customer_id'],
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
                'remarks' => $validated['remarks'],
                'discount' => $validated['discount'],
                'status' => 'pending',
                'profit' => $totalProfit,
            ]);

            // Create order items and update product stock
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['id']);
                
                // Check if enough stock is available
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                // Create order item
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

                // Update product stock
                $product->decrement('stock', $item['quantity']);
            }

            // Create order expenses if provided
            if (!empty($validated['expenses'])) {
                foreach ($validated['expenses'] as $expense) {
                    $order->expenses()->create([
                        'name' => $expense['name'],
                        'amount' => $expense['amount'],
                        'remark' => $expense['remark'] ?? null,
                    ]);
                }
            }

            // Submit to MyInvois
            // $myInvoisService = new \App\Services\MyInvoisService();
            // $myInvoisService->submitInvoice($order);

            // Call TMS service to send receipt
            // $tmsReceiptService = new TmsReceiptService();
            // $tmsReceiptService->sendReceipt($order->receipt_id);

            DB::commit();

            // Send receipt to external API (fire-and-forget)
            try {
                $tmsService = app(TmsReceiptService::class);
                if ($tmsService->isEnabled()) {
                    $tmsService->sendReceipt($this->buildReceiptPayload($order));
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to send TMS receipt', ['error' => $e->getMessage()]);
            }

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
        $order->load(['customer', 'user', 'items.product', 'expenses']);
        
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
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
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
                'delivery_name' => $order->delivery_name,
                'delivery_company_name' => $order->delivery_company_name,
                'delivery_address' => $order->delivery_address,
                'delivery_phone' => $order->delivery_phone,
                'remarks' => $order->remarks,
                'status' => $order->status,
                'payment_status' => $order->paid_amount >= $order->total ? 'paid' : 
                    ($order->paid_amount > 0 ? 'partial' : 'pending'),
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'expenses' => $order->expenses->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'name' => $expense->name,
                        'amount' => number_format($expense->amount, 2),
                        'remark' => $expense->remark,
                    ];
                }),
            ],
            'customers' => \App\Models\Customer::select('id', 'name', 'email', 'phone', 'address')->get(),
            'products' => \App\Models\Product::select('id', 'name', 'price', 'stock')->get(),
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
            'expenses' => 'nullable|array',
            'expenses.*.id' => 'nullable|integer',
            'expenses.*.name' => 'required_with:expenses|string',
            'expenses.*.amount' => 'required_with:expenses|numeric|min:0',
            'expenses.*.remark' => 'nullable|string',
            'payment_method' => 'required|in:cash,card,e-wallet,online_transfer',
            'delivery_method' => 'required|in:pickup,delivery,walk-in,shopee,tiktok,lazada',
            'delivery_cost' => 'required|numeric|min:0',
            'delivery_name' => 'nullable|string|max:255',
            'delivery_company_name' => 'nullable|string|max:255',
            'delivery_address' => 'nullable|string',
            'delivery_phone' => 'nullable|string|max:255',
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
                    $itemProfit = ($item['price'] - $product->cost_price) * $item['quantity'];
                    $totalProfit += $itemProfit;
                }
            }

            // Adjust profit based on discount
            if ($validated['discount'] > 0) {
                $totalProfit -= $validated['discount'];
            }

            // Subtract expenses from profit
            $expensesTotal = 0;
            if (!empty($validated['expenses'])) {
                foreach ($validated['expenses'] as $expense) {
                    $expensesTotal += (float) $expense['amount'];
                }
            }
            $totalProfit -= $expensesTotal;

            // Update order details
            $order->update([
                'customer_id' => $validated['customer_id'],
                'payment_method' => $validated['payment_method'],
                'delivery_method' => $validated['delivery_method'],
                'delivery_cost' => $validated['delivery_cost'],
                'delivery_name' => $validated['delivery_name'] ?? null,
                'delivery_company_name' => $validated['delivery_company_name'] ?? null,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'delivery_phone' => $validated['delivery_phone'] ?? null,
                'paid_amount' => $validated['paid_amount'],
                'due_amount' => $validated['due_amount'],
                'change_amount' => $validated['change_amount'],
                'remarks' => $validated['remarks'],
                'discount' => $validated['discount'],
                'subtotal' => collect($validated['items'])->sum('total'),
                'tax' => collect($validated['items'])->sum('total') * (settings('tax_percentage', 0) / 100),
                'total' => collect($validated['items'])->sum('total') + 
                          (collect($validated['items'])->sum('total') * (settings('tax_percentage', 0) / 100)) + 
                          $validated['delivery_cost'] -
                          $validated['discount'],
                'profit' => $totalProfit,
            ]);

            // Update order items
            // Restore stock for all existing order items before deleting
            foreach ($order->items as $oldItem) {
                if ($oldItem->product) {
                    $oldItem->product->increment('stock', $oldItem->quantity);
                }
            }
            $order->items()->delete(); // Remove existing items
            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $costPrice = $product ? $product->cost_price : 0;
                
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

                // Update product stock if product still exists
                if ($item['product_id']) {
                    $product = Product::find($item['product_id']);
                    if ($product) {
                        // Then deduct the new quantity
                        $product->decrement('stock', $item['quantity']);
                    }
                }
            }

            // Update order expenses
            $order->expenses()->delete();
            if (!empty($validated['expenses'])) {
                foreach ($validated['expenses'] as $expense) {
                    $order->expenses()->create([
                        'name' => $expense['name'],
                        'amount' => $expense['amount'],
                        'remark' => $expense['remark'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return back()->with('success', 'Order updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update order: ' . $e->getMessage());
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

        $order->update(['status' => $validated['status'], 'payment_status' => $validated['status']]);

        return back()->with('success', 'Order status updated successfully');
    }

    public function updateDelivery(Request $request, Order $order)
    {
        $validated = $request->validate([
            'delivery_name' => 'nullable|string|max:255',
            'delivery_company_name' => 'nullable|string|max:255',
            'delivery_address' => 'nullable|string',
            'delivery_phone' => 'nullable|string|max:255',
        ]);

        $order->update([
            'delivery_name' => $validated['delivery_name'] ?? null,
            'delivery_company_name' => $validated['delivery_company_name'] ?? null,
            'delivery_address' => $validated['delivery_address'] ?? null,
            'delivery_phone' => $validated['delivery_phone'] ?? null,
        ]);

        return back()->with('success', 'Delivery information updated successfully');
    }

    public function updateCustomer(Request $request, Order $order)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        // If customer_id exists, update the existing customer
        if ($validated['customer_id']) {
            $customer = Customer::findOrFail($validated['customer_id']);
            $customer->update([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);
            // Update order to use this customer
            $order->update(['customer_id' => $validated['customer_id']]);
        } else {
            // Create a new customer and assign to order
            $customer = Customer::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'status' => 'active',
            ]);
            $order->update(['customer_id' => $customer->id]);
        }

        return back()->with('success', 'Customer information updated successfully');
    }

    public function addToConsolidation(Order $order)
    {
        $queueService = new ConsolidationQueueService();
        
        // Check if order is already in queue
        if ($queueService->isInQueue($order->id)) {
            return back()->with('info', 'Order is already in consolidation queue');
        }

        // Check if order already has MyInvois invoice
        if ($order->myInvoisInvoice) {
            return back()->with('info', 'Order has already been pushed to MyInvois');
        }

        // Add to queue
        if ($queueService->addOrder($order)) {
            return back()->with('success', 'Order added to consolidation queue successfully');
        }

        return back()->with('error', 'Failed to add order to consolidation queue');
    }

    public function destroy(Order $order)
    {
        try {
            DB::beginTransaction();

            // Send void receipt to external API (fire-and-forget)
            try {
                $tmsService = app(TmsReceiptService::class);
                if ($tmsService->isEnabled()) {
                    $tmsService->sendReceipt($this->buildReceiptPayload($order, true));
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to send VOID TMS receipt', ['error' => $e->getMessage()]);
            }

            // Restore product stock
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            // Delete the order (this will also delete order items due to cascade)
            $order->delete();

            DB::commit();

            return back()->with('success', 'Order deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete order: ' . $e->getMessage());
        }
    }

    public function exportCsv()
    {
        $orders = \App\Models\Order::with(['customer', 'user', 'items.product'])
            ->when(request('search'), function($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                      ->orWhereHas('customer', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when(request('remark'), function($query, $remark) {
                $query->whereHas('items', function($q) use ($remark) {
                    $q->where('remark', 'like', "%{$remark}%");
                });
            })
            ->when(request('start_date'), function($query, $startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            })
            ->when(request('end_date'), function($query, $endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            })
            ->when(request('product_search'), function($query, $productSearch) {
                $query->whereHas('items.product', function($q) use ($productSearch) {
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
            'id', 'order_number', 'customer_name', 'cashier_name', 'subtotal', 'tax', 'total', 'profit', 'paid_amount', 'due_amount', 'change_amount', 'payment_method', 'delivery_method', 'status', 'created_at', 'item_remarks'
        ];

        $callback = function() use ($orders, $columns) {
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

    protected function buildReceiptPayload(Order $order, bool $void = false): array
    {
        $settings = ShopSettings::first();
        $discountPercent = $order->subtotal > 0 ? round(($order->discount / $order->subtotal) * 100, 2) : 0.0;

        return [
            'ReceiptNo'           => (string) $order->id,
            'ReceiptDateAndTime2' => $order->created_at->format('Y-m-d H:i:s'),
            'SubTotal'            => (float) $order->subtotal,
            'DiscountPercent'     => (float) $discountPercent,
            'DiscountAmount'      => (float) $order->discount,
            'GstPercent'          => $settings ? (float) $settings->tax_percentage : 0.0,
            'GstAmount'           => (float) $order->tax,
            'GrandTotal'          => (float) $order->total,
            'IsVoid'              => $void,
        ];
    }
} 