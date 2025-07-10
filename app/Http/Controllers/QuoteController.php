<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\ShopSettings;

class QuoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Quote::with(['customer', 'user']);
        if ($request->search) {
            $query->where('id', $request->search)
                ->orWhereHas('customer', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
        }
        if ($request->customer) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->customer . '%');
            });
        }
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->sort_column) {
            $query->orderBy($request->sort_column, $request->sort_direction === 'desc' ? 'desc' : 'asc');
        } else {
            $query->latest();
        }
        $quotes = $query->paginate(20)->withQueryString();
        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::all();
        $products = Product::all();
        return Inertia::render('Quotes/Create', [
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'subtotal' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'delivery_cost' => 'nullable|numeric',
            'delivery_method' => 'nullable|string',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.total' => 'required|numeric',
            'items.*.remark' => 'nullable|string',
            'items.*.custom_fields' => 'nullable|array',
            'status' => 'string|in:draft,sent,accepted,rejected',
        ]);
        $quote = Quote::create([
            'user_id' => Auth::id(),
            'customer_id' => $data['customer_id'] ?? null,
            'subtotal' => $data['subtotal'],
            'tax' => $data['tax'],
            'total' => $data['total'],
            'discount' => $data['discount'] ?? 0,
            'delivery_cost' => $data['delivery_cost'] ?? 0,
            'delivery_method' => $data['delivery_method'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);
        foreach ($data['items'] as $item) {
            $quote->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['total'],
                'remark' => $item['remark'] ?? null,
                'custom_fields' => $item['custom_fields'] ?? null,
            ]);
        }
        return redirect()->route('quotes.index')->with('success', 'Quote created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $quote = Quote::with(['customer', 'user', 'items.product'])->findOrFail($id);
        return Inertia::render('Quotes/Show', [
            'quote' => $quote
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $quote = Quote::with(['customer', 'user', 'items.product'])->findOrFail($id);
        $customers = Customer::all();
        $products = Product::all();
        return Inertia::render('Quotes/Edit', [
            'quote' => $quote,
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'subtotal' => 'required|numeric',
            'tax' => 'required|numeric',
            'total' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'delivery_cost' => 'nullable|numeric',
            'delivery_method' => 'nullable|string',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:quote_items,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.total' => 'required|numeric',
            'items.*.remark' => 'nullable|string',
            'items.*.custom_fields' => 'nullable|array',
            'status' => 'string|in:draft,sent,accepted,rejected',
        ]);
        $quote = Quote::findOrFail($id);
        $quote->update([
            'customer_id' => $data['customer_id'] ?? null,
            'subtotal' => $data['subtotal'],
            'tax' => $data['tax'],
            'total' => $data['total'],
            'discount' => $data['discount'] ?? 0,
            'delivery_cost' => $data['delivery_cost'] ?? 0,
            'delivery_method' => $data['delivery_method'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'status' => $data['status'],
        ]);
        // Sync items
        $existingItemIds = $quote->items()->pluck('id')->toArray();
        $submittedItemIds = collect($data['items'])->pluck('id')->filter()->toArray();
        // Delete removed items
        $toDelete = array_diff($existingItemIds, $submittedItemIds);
        if (!empty($toDelete)) {
            QuoteItem::whereIn('id', $toDelete)->delete();
        }
        // Update or create items
        foreach ($data['items'] as $item) {
            if (!empty($item['id'])) {
                $quoteItem = QuoteItem::find($item['id']);
                if ($quoteItem) {
                    $quoteItem->update([
                        'product_id' => $item['product_id'] ?? null,
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $item['total'],
                        'remark' => $item['remark'] ?? null,
                        'custom_fields' => $item['custom_fields'] ?? null,
                    ]);
                }
            } else {
                $quote->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total'],
                    'remark' => $item['remark'] ?? null,
                    'custom_fields' => $item['custom_fields'] ?? null,
                ]);
            }
        }
        return redirect()->route('quotes.index')->with('success', 'Quote updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $quote = Quote::findOrFail($id);
        $quote->items()->delete();
        $quote->delete();
        return redirect()->route('quotes.index')->with('success', 'Quote deleted successfully.');
    }

    public function pdf($id)
    {
        $quote = Quote::with(['customer', 'user', 'items.product'])->findOrFail($id);
        $settings = \App\Models\ShopSettings::first();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('quotes.pdf', ['quote' => $quote, 'settings' => $settings, 'user' => Auth::user()]);
        return $pdf->stream('quote-'.$quote->id.'.pdf');
    }
}
