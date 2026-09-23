<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Customers/Index', [
            'customers' => Customer::query()
                ->when($request->input('search'), function($query, $search) {
                    $query->where(function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(Request $request)
    {
        // Accept local formats (012-345 6789) and store E.164 (+60123456789).
        $request->merge(['phone' => Customer::normalizePhone($request->input('phone'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{1,14}$/'],
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'state_code' => 'nullable|string|max:2',
            'country' => 'nullable|string|max:3',
            'tin' => 'nullable|string|max:50',
            'brn' => 'nullable|string|max:50',
            'nric' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive',
        ]);

        Customer::create($validated);

        return redirect()->route('customers.index')
            ->with('success', 'Customer created successfully');
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        // Accept local formats (012-345 6789) and store E.164 (+60123456789).
        $request->merge(['phone' => Customer::normalizePhone($request->input('phone'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{1,14}$/'],
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'state_code' => 'nullable|string|max:2',
            'country' => 'nullable|string|max:3',
            'tin' => 'nullable|string|max:50',
            'brn' => 'nullable|string|max:50',
            'nric' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')
            ->with('success', 'Customer updated successfully');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully');
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');
        // Phones are stored as +60…; let "012 345" match "+6012345…".
        $digits = ltrim(preg_replace('/\D+/', '', $query), '0');

        return Customer::where('status', 'active')
            ->where(function($q) use ($query, $digits) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
                if (strlen($digits) >= 3) {
                    $q->orWhere('phone', 'like', "%{$digits}%");
                }
            })
            ->select('id', 'name', 'email', 'phone')
            ->limit(10)
            ->get();
    }

    /**
     * Counter quick-add used by the sale and repair intake screens: phone is the
     * identity at a phone shop, so reuse an existing customer with that number.
     */
    public function quickStore(Request $request)
    {
        $request->merge(['phone' => Customer::normalizePhone($request->input('phone'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,14}$/'],
            'email' => 'nullable|email',
        ]);

        $customer = Customer::where('phone', $validated['phone'])->first();

        if ($customer) {
            if (! empty($validated['email']) && empty($customer->email)) {
                $customer->update(['email' => $validated['email']]);
            }
        } else {
            if (! empty($validated['email']) && Customer::where('email', $validated['email'])->exists()) {
                $validated['email'] = null;
            }
            $customer = Customer::create($validated + ['status' => 'active']);
        }

        return response()->json($customer->only(['id', 'name', 'email', 'phone']));
    }
}
