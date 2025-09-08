<?php

namespace App\Http\Controllers;

use App\Models\AccountingCategory;
use App\Models\AccountingEntry;
use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'category_id', 'type', 'start_date', 'end_date', 'ar_ap_type', 'is_payroll']);

        $entries = AccountingEntry::with(['category'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('description', 'like', "%{$search}%");
            })
            ->when($filters['category_id'] ?? null, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($filters['type'] ?? null, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($filters['ar_ap_type'] ?? null, function ($query, $arAp) {
                $query->where('ar_ap_type', $arAp);
            })
            ->when(isset($filters['is_payroll']), function ($query) use ($filters) {
                if ($filters['is_payroll'] !== null && $filters['is_payroll'] !== '') {
                    $query->where('is_payroll', (bool) $filters['is_payroll']);
                }
            })
            ->when($filters['start_date'] ?? null, function ($query, $startDate) {
                $query->whereDate('date', '>=', $startDate);
            })
            ->when($filters['end_date'] ?? null, function ($query, $endDate) {
                $query->whereDate('date', '<=', $endDate);
            })
            ->latest('date')
            ->paginate(15)
            ->through(function ($entry) {
                return [
                    'id' => $entry->id,
                    'date' => $entry->date?->format('Y-m-d'),
                    'due_date' => $entry->due_date?->format('Y-m-d'),
                    'amount' => number_format($entry->amount, 2),
                    'type' => $entry->type,
                    'ar_ap_type' => $entry->ar_ap_type,
                    'category' => $entry->category ? [
                        'id' => $entry->category->id,
                        'name' => $entry->category->name,
                        'type' => $entry->category->type,
                        'subtype' => $entry->category->subtype,
                    ] : null,
                    'description' => $entry->description,
                    'party_name' => $entry->party_name,
                    'reference' => $entry->reference,
                    'is_payroll' => (bool) $entry->is_payroll,
                ];
            })
            ->withQueryString();

        // PnL summary
        $start = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $filters['end_date'] ?? now()->endOfMonth()->toDateString();

        $income = AccountingEntry::whereBetween('date', [$start, $end])
            ->where('type', 'credit')
            ->sum('amount');

        $expense = AccountingEntry::whereBetween('date', [$start, $end])
            ->where('type', 'debit')
            ->sum('amount');

        return Inertia::render('Accounting/Index', [
            'entries' => $entries,
            'categories' => AccountingCategory::orderBy('name')->get(['id', 'name', 'type', 'subtype']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'summary' => [
                'income' => number_format($income, 2),
                'expense' => number_format($expense, 2),
                'profit' => number_format($income - $expense, 2),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'due_date' => 'nullable|date',
            'category_id' => 'nullable|exists:accounting_categories,id',
            'type' => 'required|in:credit,debit',
            'ar_ap_type' => 'nullable|in:AR,AP',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'party_name' => 'nullable|string|max:255',
            'party_id' => 'nullable|integer',
            'reference' => 'nullable|string|max:255',
            'is_payroll' => 'nullable|boolean',
        ]);

        $validated['created_by'] = auth()->id();

        AccountingEntry::create($validated);

        return back()->with('success', 'Entry created');
    }

    public function update(Request $request, AccountingEntry $entry)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'category_id' => 'nullable|exists:accounting_categories,id',
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $entry->update($validated);

        return back()->with('success', 'Entry updated');
    }

    public function destroy(AccountingEntry $entry)
    {
        $entry->delete();
        return back()->with('success', 'Entry deleted');
    }

    public function categoriesIndex()
    {
        return Inertia::render('Accounting/Categories', [
            'categories' => AccountingCategory::orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function categoriesStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'subtype' => 'nullable|in:general,payroll,cogs',
            'description' => 'nullable|string',
        ]);
        AccountingCategory::create($validated);
        return back()->with('success', 'Category created');
    }

    public function categoriesUpdate(Request $request, AccountingCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'subtype' => 'nullable|in:general,payroll,cogs',
            'description' => 'nullable|string',
        ]);
        $category->update($validated);
        return back()->with('success', 'Category updated');
    }

    public function categoriesDestroy(AccountingCategory $category)
    {
        $category->delete();
        return back()->with('success', 'Category deleted');
    }

    // Manual sync from orders: create accounting entries for order totals as income and order expenses as expenses
    public function syncFromOrders(Request $request)
    {
        // Always sync all orders

        $incomeCategory = AccountingCategory::firstOrCreate([
            'name' => 'Sales Income',
            'type' => 'income',
        ], [ 'description' => 'Synced from Orders total' ]);

        $expenseCategory = AccountingCategory::firstOrCreate([
            'name' => 'Order Expenses',
            'type' => 'expense',
        ], [ 'description' => 'Synced from Order service expenses' ]);

        $orders = Order::with(['expenses'])->get();

        DB::beginTransaction();
        try {
            $processedOrderIds = [];
            foreach ($orders as $order) {
                $processedOrderIds[] = $order->id;
                // Create or update income entry per order id
                AccountingEntry::updateOrCreate([
                    'order_id' => $order->id,
                    'type' => 'credit',
                ], [
                    'category_id' => $incomeCategory->id,
                    'date' => $order->created_at->toDateString(),
                    'amount' => $order->total,
                    'description' => 'Sales for Order #' . $order->id,
                    'created_by' => auth()->id(),
                ]);

                // Sum order expenses and save as a single debit entry
                $expensesTotal = $order->expenses->sum('amount');
                if ($expensesTotal > 0) {
                    AccountingEntry::updateOrCreate([
                        'order_id' => $order->id,
                        'type' => 'debit',
                    ], [
                        'category_id' => $expenseCategory->id,
                        'date' => $order->created_at->toDateString(),
                        'amount' => $expensesTotal,
                        'description' => 'Order expenses for Order #' . $order->id,
                        'created_by' => auth()->id(),
                    ]);
                } else {
                    // Remove any existing expense entry for orders that now have zero expenses
                    AccountingEntry::where('order_id', $order->id)
                        ->where('type', 'debit')
                        ->delete();
                }
            }

            // Cleanup orphaned entries where the linked order no longer exists in the period
            AccountingEntry::whereNotNull('order_id')
                ->whereDoesntHave('order')
                ->delete();
            DB::commit();
            // redirect the page or refresh the page    
            return redirect()->route('accounting.index')->with('success', 'Sync completed successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }
}


