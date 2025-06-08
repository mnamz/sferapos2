<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShopSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
            ->when(request('sort_column'), function($query, $column) {
                $direction = request('sort_direction', 'asc');
                
                // Map frontend column names to database column names
                $columnMap = [
                    'id' => 'orders.id',
                    'customer_name' => 'customers.name',
                    'total_amount' => 'orders.total',
                    'items_count' => 'orders.id',
                    'payment_method' => 'orders.payment_method',
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
            'filters' => request()->only(['search', 'remark']),
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
        $order->load(['customer', 'user', 'items.product']);
        
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
        $order->load(['customer', 'user', 'items.product']);
        
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
                'remarks' => $order->remarks,
                'status' => $order->status,
                'payment_status' => $order->paid_amount >= $order->total ? 'paid' : 
                    ($order->paid_amount > 0 ? 'partial' : 'pending'),
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
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
                    $itemProfit = ($item['price'] - $product->cost_price) * $item['quantity'];
                    $totalProfit += $itemProfit;
                }
            }

            // Adjust profit based on discount
            if ($validated['discount'] > 0) {
                $totalProfit -= $validated['discount'];
            }

            // Update order details
            $order->update([
                'customer_id' => $validated['customer_id'],
                'payment_method' => $validated['payment_method'],
                'delivery_method' => $validated['delivery_method'],
                'delivery_cost' => $validated['delivery_cost'],
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
                        // First restore the old quantity
                        $oldItem = $order->items()->where('product_id', $item['product_id'])->first();
                        if ($oldItem) {
                            $product->increment('stock', $oldItem->quantity);
                        }
                        // Then deduct the new quantity
                        $product->decrement('stock', $item['quantity']);
                    }
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

    public function destroy(Order $order)
    {
        try {
            DB::beginTransaction();

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
} 