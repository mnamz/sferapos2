<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\Supplier;
use App\Services\ProductSerialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Products/Index', [
            'products' => Product::query()
                ->with(['category:id,name', 'supplier:id,name'])
                ->when($request->input('search'), function ($query, $search) {
                    $lower = strtolower($search);
                    $query->where(function ($q) use ($lower) {
                        $q->whereNameMatchesNormalized($lower)
                            ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lower}%"])
                            ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$lower}%"])
                            ->orWhereHas('category', function ($cq) use ($lower) {
                                $cq->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"]);
                            })
                            ->orWhereHas('supplier', function ($sq) use ($lower) {
                                $sq->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"]);
                            });
                    });
                })
                ->when($request->input('filter') === 'low-stock', function ($query) {
                    $query->where('stock', '<=', 10);
                })
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search', 'filter']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Category::where('status', 'active')->get(),
            'suppliers' => Supplier::where('status', 'active')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'barcode' => 'nullable|string|unique:products',
            'image' => 'nullable|image|max:1024', // max 1MB
            'status' => 'required|in:active,inactive',
            'serial_tracked' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        if ($request->boolean('serial_tracked')) {
            $validated['stock'] = 0;
        }

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Product created successfully');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Edit', [
            'product' => $product->load('supplier'),
            'categories' => Category::where('status', 'active')->get(),
            'suppliers' => Supplier::where('status', 'active')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'barcode' => ['nullable', 'string', Rule::unique('products')->ignore($product->id)],
            'image' => 'nullable|image|max:1024',
            'status' => 'required|in:active,inactive',
            'serial_tracked' => 'boolean',
        ]);

        if ($request->boolean('serial_tracked') && ! $product->serial_tracked && $product->stock > 0) {
            return back()->with('error', 'Reduce stock to zero before enabling serial tracking.');
        }

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Product updated successfully');
    }

    public function destroy(Product $product)
    {
        // Delete image if exists
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully');
    }

    public function getPosProducts()
    {
        $products = Product::with('category')
            ->where('status', 'active')
            ->where('stock', '>', 0)
            ->select('id', 'name', 'price', 'stock', 'barcode', 'category_id', 'image', 'serial_tracked')
            ->get();

        $categories = Category::where('status', 'active')
            ->select('id', 'name')
            ->get();

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function show(Product $product)
    {
        return Inertia::render('Products/Show', [
            'product' => $product->load(['category', 'supplier', 'serials' => fn ($q) => $q->where('status', 'available')->orderBy('serial_number')]),
        ]);
    }

    public function lowStock(Request $request)
    {
        return Inertia::render('Products/LowStock', [
            'products' => Product::query()
                ->with(['category:id,name', 'supplier:id,name'])
                ->where('stock', '<=', 10)
                ->where('status', 'active')
                ->when($request->input('search'), function ($query, $search) {
                    $lower = strtolower($search);
                    $query->where(function ($q) use ($lower) {
                        $q->whereNameMatchesNormalized($lower)
                            ->orWhereRaw('LOWER(description) LIKE ?', ["%{$lower}%"])
                            ->orWhereRaw('LOWER(barcode) LIKE ?', ["%{$lower}%"])
                            ->orWhereHas('category', function ($cq) use ($lower) {
                                $cq->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"]);
                            });
                    });
                })
                ->orderBy('stock')
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function adjustStock(Request $request, Product $product)
    {
        if ($product->serial_tracked) {
            return back()->with('error', 'Use serial management for this product.');
        }

        $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:restock,withdraw',
            'notes' => 'nullable|string|max:255',
        ]);

        $quantity = $request->quantity;
        if ($request->type === 'withdraw') {
            $quantity = -$quantity;
        }

        $oldStock = $product->stock;
        $product->stock += $quantity;

        if ($product->stock < 0) {
            return back()->with('error', 'Insufficient stock for withdrawal.');
        }

        // Create audit data
        $auditData = [
            'old_values' => ['stock' => $oldStock],
            'new_values' => [
                'stock' => $product->stock,
                'adjustment' => $quantity,
                'adjustment_type' => $request->type,
                'adjustment_notes' => $request->notes,
            ],
            'event' => 'updated',
            'auditable_type' => get_class($product),
            'auditable_id' => $product->id,
            'user_id' => auth()->id(),
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        $product->save();

        // Create the audit record
        $product->audits()->create($auditData);

        return back()->with('success', 'Stock updated successfully.');
    }

    public function getSerials(Product $product)
    {
        return response()->json([
            'serials' => $product->serials()
                ->where('status', 'available')
                ->orderBy('serial_number')
                ->get(['id', 'serial_number', 'status']),
        ]);
    }

    public function addSerials(Request $request, Product $product, ProductSerialService $service)
    {
        if (! $product->serial_tracked) {
            return back()->with('error', 'This product is not serial-tracked.');
        }

        $validated = $request->validate([
            'serials' => ['required', 'array', 'min:1'],
            'serials.*' => [
                'required', 'string', 'max:255', 'distinct',
                \Illuminate\Validation\Rule::unique('product_serials', 'serial_number')->whereNull('deleted_at'),
            ],
        ]);

        $serials = array_map('trim', $validated['serials']);
        $service->addSerials($product, $serials);

        return back()->with('success', 'Serials added successfully.');
    }

    public function removeSerial(Product $product, ProductSerial $serial, ProductSerialService $service)
    {
        if ($serial->product_id !== $product->id) {
            abort(404);
        }

        if ($serial->status !== 'available') {
            return back()->with('error', 'Only available serials can be removed.');
        }

        $service->removeSerial($serial);

        return back()->with('success', 'Serial removed successfully.');
    }

    public function inventoryCost(Request $request)
    {
        $products = Product::query()
            ->with(['category:id,name'])
            ->select('id', 'name', 'cost_price', 'stock', 'category_id')
            ->when($request->input('search'), function ($query, $search) {
                $lower = strtolower($search);
                $query->where(function ($q) use ($lower) {
                    $q->whereNameMatchesNormalized($lower)
                        ->orWhereHas('category', function ($cq) use ($lower) {
                            $cq->whereRaw('LOWER(name) LIKE ?', ["%{$lower}%"]);
                        });
                });
            })
            ->when($request->input('sort'), function ($query, $sort) use ($request) {
                $direction = $request->input('direction', 'asc');
                switch ($sort) {
                    case 'name':
                        $query->orderBy('name', $direction);
                        break;
                    case 'cost_price':
                        $query->orderBy('cost_price', $direction);
                        break;
                    case 'stock':
                        $query->orderBy('stock', $direction);
                        break;
                    case 'total_cost':
                        $query->orderByRaw('cost_price * stock '.$direction);
                        break;
                    default:
                        $query->orderBy('name', 'asc');
                }
            }, function ($query) {
                $query->orderBy('name', 'asc');
            })
            ->paginate(10)
            ->withQueryString();

        $totalInventoryCost = (float) Product::sum(\DB::raw('cost_price * stock'));

        return Inertia::render('Products/InventoryCost', [
            'products' => $products,
            'totalInventoryCost' => $totalInventoryCost,
            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    public function exportInventoryCost(Request $request)
    {
        $products = Product::query()
            ->select('name', 'cost_price', 'stock', 'category_id')
            ->with('category:id,name')
            ->when($request->input('search'), function ($query, $search) {
                $lower = strtolower($search);
                $query->whereNameMatchesNormalized($lower);
            })
            ->when($request->input('sort'), function ($query, $sort) use ($request) {
                $direction = $request->input('direction', 'asc');
                if ($sort === 'total_cost') {
                    $query->orderByRaw("cost_price * stock {$direction}");
                } else {
                    $query->orderBy($sort, $direction);
                }
            })
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory-cost.csv"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            // Add headers
            fputcsv($file, ['Product Name', 'Category', 'Cost Price', 'Stock', 'Total Cost']);

            // Add data
            foreach ($products as $product) {
                fputcsv($file, [
                    $product->name,
                    $product->category->name,
                    number_format($product->cost_price, 2),
                    $product->stock,
                    number_format($product->cost_price * $product->stock, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if ($query === '') {
            return collect();
        }

        $lower = strtolower($query);

        return Product::query()
            ->select('id', 'name', 'price', 'stock', 'barcode')
            ->whereNameMatchesNormalized($lower)
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function report(Request $request)
    {
        $startDate = $request->input('start_date', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', \Carbon\Carbon::now()->format('Y-m-d'));

        // Get products sold within the date range
        $query = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('orders.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('orders.status', '!=', 'cancelled')
            ->when($request->input('search'), function ($query, $search) {
                $lower = strtolower($search);
                $query->where(function ($q) use ($lower) {
                    $q->whereRaw('LOWER(order_items.product_name) LIKE ?', ["%{$lower}%"])
                        ->orWhereRaw('LOWER(categories.name) LIKE ?', ["%{$lower}%"]);
                });
            })
            ->select(
                'order_items.product_id',
                'order_items.product_name',
                'categories.name as category_name',
                \DB::raw('SUM(order_items.quantity) as total_quantity'),
                \DB::raw('AVG(order_items.price) as avg_price'),
                \DB::raw('AVG(order_items.cost_price) as avg_cost_price'),
                \DB::raw('SUM(order_items.total) as total_revenue'),
                \DB::raw('SUM(order_items.cost_price * order_items.quantity) as total_cost'),
                \DB::raw('SUM(order_items.profit) as total_profit')
            )
            ->groupBy('order_items.product_id', 'order_items.product_name', 'categories.name');

        // Apply sorting
        $sortColumn = $request->input('sort_column', 'total_revenue');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortColumn, $sortDirection);

        $products = $query->paginate(15)->withQueryString();

        // Calculate summary statistics
        $summary = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('orders.status', '!=', 'cancelled')
            ->select(
                \DB::raw('COUNT(DISTINCT order_items.product_id) as total_products'),
                \DB::raw('SUM(order_items.quantity) as total_quantity'),
                \DB::raw('SUM(order_items.total) as total_revenue'),
                \DB::raw('SUM(order_items.cost_price * order_items.quantity) as total_cost'),
                \DB::raw('SUM(order_items.profit) as total_profit')
            )
            ->first();

        return Inertia::render('Products/Report', [
            'products' => $products,
            'summary' => $summary,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $request->input('search'),
                'sort_column' => $sortColumn,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function exportReport(Request $request)
    {
        $startDate = $request->input('start_date', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', \Carbon\Carbon::now()->format('Y-m-d'));

        $products = \DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('orders.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('orders.status', '!=', 'cancelled')
            ->when($request->input('search'), function ($query, $search) {
                $lower = strtolower($search);
                $query->where(function ($q) use ($lower) {
                    $q->whereRaw('LOWER(order_items.product_name) LIKE ?', ["%{$lower}%"])
                        ->orWhereRaw('LOWER(categories.name) LIKE ?', ["%{$lower}%"]);
                });
            })
            ->select(
                'order_items.product_name',
                'categories.name as category_name',
                \DB::raw('SUM(order_items.quantity) as total_quantity'),
                \DB::raw('AVG(order_items.price) as avg_price'),
                \DB::raw('AVG(order_items.cost_price) as avg_cost_price'),
                \DB::raw('SUM(order_items.total) as total_revenue'),
                \DB::raw('SUM(order_items.cost_price * order_items.quantity) as total_cost'),
                \DB::raw('SUM(order_items.profit) as total_profit')
            )
            ->groupBy('order_items.product_id', 'order_items.product_name', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="product-report-'.$startDate.'-to-'.$endDate.'.csv"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            // Add headers
            fputcsv($file, [
                'Product Name',
                'Category',
                'Total Quantity Sold',
                'Average Price',
                'Average Cost Price',
                'Total Revenue',
                'Total Cost',
                'Total Profit',
            ]);

            // Add data
            foreach ($products as $product) {
                fputcsv($file, [
                    $product->product_name,
                    $product->category_name ?? 'N/A',
                    $product->total_quantity,
                    number_format($product->avg_price, 2),
                    number_format($product->avg_cost_price, 2),
                    number_format($product->total_revenue, 2),
                    number_format($product->total_cost, 2),
                    number_format($product->total_profit, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
