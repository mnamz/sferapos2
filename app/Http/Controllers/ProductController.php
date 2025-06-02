<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Products/Index', [
            'products' => Product::query()
                ->with(['category:id,name', 'supplier:id,name'])
                ->when($request->input('search'), function($query, $search) {
                    $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('barcode', 'like', "%{$search}%")
                          ->orWhereHas('category', function($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%");
                          })
                          ->orWhereHas('supplier', function($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%");
                          });
                    });
                })
                ->when($request->input('filter') === 'low-stock', function($query) {
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
            'suppliers' => Supplier::where('status', 'active')->get()
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
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Product created successfully');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Edit', [
            'product' => $product->load('supplier'),
            'categories' => Category::where('status', 'active')->get(),
            'suppliers' => Supplier::where('status', 'active')->get()
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
        ]);

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
            ->select('id', 'name', 'price', 'stock', 'barcode', 'category_id', 'image')
            ->get();

        $categories = Category::where('status', 'active')
            ->select('id', 'name')
            ->get();

        return response()->json([
            'products' => $products,
            'categories' => $categories
        ]);
    }

    public function show(Product $product)
    {
        return Inertia::render('Products/Show', [
            'product' => $product->load(['category', 'supplier'])
        ]);
    }

    public function lowStock(Request $request)
    {
        return Inertia::render('Products/LowStock', [
            'products' => Product::query()
                ->with(['category:id,name', 'supplier:id,name'])
                ->where('stock', '<=', 10)
                ->where('status', 'active')
                ->when($request->input('search'), function($query, $search) {
                    $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('barcode', 'like', "%{$search}%")
                          ->orWhereHas('category', function($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%");
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
        $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:restock,withdraw',
            'notes' => 'nullable|string|max:255'
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
                'adjustment_notes' => $request->notes
            ],
            'event' => 'updated',
            'auditable_type' => get_class($product),
            'auditable_id' => $product->id,
            'user_id' => auth()->id(),
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];

        $product->save();
        
        // Create the audit record
        $product->audits()->create($auditData);

        return back()->with('success', 'Stock updated successfully.');
    }

    public function inventoryCost(Request $request)
    {
        $products = Product::query()
            ->with(['category:id,name'])
            ->select('id', 'name', 'cost_price', 'stock', 'category_id')
            ->when($request->input('search'), function($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('category', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($request->input('sort'), function($query, $sort) use ($request) {
                $direction = $request->input('direction', 'asc');
                switch($sort) {
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
                        $query->orderByRaw('cost_price * stock ' . $direction);
                        break;
                    default:
                        $query->orderBy('name', 'asc');
                }
            }, function($query) {
                $query->orderBy('name', 'asc');
            })
            ->paginate(10)
            ->withQueryString();

        $totalInventoryCost = (float) Product::sum(\DB::raw('cost_price * stock'));

        return Inertia::render('Products/InventoryCost', [
            'products' => $products,
            'totalInventoryCost' => $totalInventoryCost,
            'filters' => $request->only(['search', 'sort', 'direction'])
        ]);
    }

    public function exportInventoryCost(Request $request)
    {
        $products = Product::query()
            ->select('name', 'cost_price', 'stock', 'category_id')
            ->with('category:id,name')
            ->when($request->input('search'), function($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->input('sort'), function($query, $sort) use ($request) {
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

        $callback = function() use ($products) {
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
                    number_format($product->cost_price * $product->stock, 2)
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
} 