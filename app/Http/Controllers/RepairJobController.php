<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RepairJob;
use App\Models\RepairJobItem;
use App\Models\ShopSettings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RepairJobController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'open');

        $jobs = RepairJob::query()
            ->with(['customer:id,name,phone', 'technician:id,name'])
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'overdue', fn ($q) => $q->open()->where('status', '!=', 'ready')->whereNotNull('promised_at')->where('promised_at', '<', now()))
            ->when(array_key_exists($status, RepairJob::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($request->input('technician'), fn ($q, $t) => $q->where('technician_id', $t))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('job_number', 'like', "%{$search}%")
                        ->orWhere('imei', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('issue', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (RepairJob $job) => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'customer' => $job->customer?->only(['id', 'name', 'phone']),
                'device' => $job->device_label,
                'device_type' => $job->device_type,
                'imei' => $job->imei,
                'issue' => $job->issue,
                'status' => $job->status,
                'priority' => $job->priority,
                'technician' => $job->technician?->name,
                'estimated_cost' => (float) $job->estimated_cost,
                'deposit' => (float) $job->deposit,
                'promised_at' => $job->promised_at?->format('Y-m-d H:i'),
                'overdue' => $job->promised_at && $job->promised_at->isPast() && in_array($job->status, RepairJob::OPEN_STATUSES) && $job->status !== 'ready',
                'created_at' => $job->created_at->format('Y-m-d H:i'),
                'age' => $job->created_at->diffForHumans(null, true),
            ]);

        $counts = RepairJob::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Repairs/Index', [
            'jobs' => $jobs,
            'filters' => ['status' => $status] + $request->only(['search', 'technician']),
            'statuses' => RepairJob::STATUSES,
            'counts' => $counts,
            'openCount' => RepairJob::open()->count(),
            'overdueCount' => RepairJob::open()->where('status', '!=', 'ready')->whereNotNull('promised_at')->where('promised_at', '<', now())->count(),
            'technicians' => $this->technicians(),
        ]);
    }

    public function create(Request $request)
    {
        $prefillCustomer = $request->input('customer')
            ? Customer::select('id', 'name', 'email', 'phone')->find($request->input('customer'))
            : null;

        return Inertia::render('Repairs/Form', $this->formOptions() + [
            'job' => null,
            'prefillCustomer' => $prefillCustomer,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateJob($request);

        $job = DB::transaction(function () use ($data) {
            $job = RepairJob::create($data + [
                'job_number' => RepairJob::nextJobNumber(),
                'user_id' => auth()->id(),
                'status' => 'received',
            ]);

            $job->logs()->create([
                'user_id' => auth()->id(),
                'to_status' => 'received',
                'note' => 'Device received at counter'.($job->deposit > 0 ? " · deposit RM{$job->deposit} ({$job->deposit_method})" : ''),
                'customer_visible' => true,
            ]);

            return $job;
        });

        return redirect()->route('repairs.show', $job)->with('success', "Repair job {$job->job_number} created");
    }

    public function show(RepairJob $repair)
    {
        $repair->load(['customer', 'receivedBy:id,name', 'technician:id,name', 'items.product:id,name,stock,type', 'logs.user:id,name', 'order']);
        $settings = ShopSettings::first();

        return Inertia::render('Repairs/Show', [
            'job' => $this->present($repair) + [
                'passcode' => $repair->passcode,
                'internal_notes' => $repair->internal_notes,
                'received_by' => $repair->receivedBy?->name,
                'technician_id' => $repair->technician_id,
                'items' => $repair->items->map(fn (RepairJobItem $i) => [
                    'id' => $i->id,
                    'product_id' => $i->product_id,
                    'name' => $i->name,
                    'item_type' => $i->item_type,
                    'quantity' => $i->quantity,
                    'price' => (float) $i->price,
                    'total' => (float) $i->price * $i->quantity,
                    'stock' => $i->product?->type !== 'service' ? $i->product?->stock : null,
                    'warranty_days' => $i->warranty_days,
                ])->values(),
                'logs' => $repair->logs->map(fn ($l) => [
                    'id' => $l->id,
                    'user' => $l->user?->name,
                    'from_status' => $l->from_status,
                    'to_status' => $l->to_status,
                    'note' => $l->note,
                    'customer_visible' => $l->customer_visible,
                    'created_at' => $l->created_at->format('Y-m-d H:i'),
                    'ago' => $l->created_at->diffForHumans(),
                ])->values(),
                'order' => $repair->order ? [
                    'id' => $repair->order->id,
                    'invoice' => $repair->order->formatted_invoice_number,
                    'total' => (float) $repair->order->total,
                    'due' => (float) $repair->order->due_amount,
                ] : null,
                'warranty_expires_at' => $repair->warrantyExpiresAt()?->format('Y-m-d'),
                'under_warranty' => $repair->isUnderWarranty(),
            ],
            'history' => $repair->imei
                ? RepairJob::where('imei', $repair->imei)->whereKeyNot($repair->id)->latest('id')->limit(10)
                    ->get(['id', 'job_number', 'issue', 'status', 'created_at', 'collected_at', 'warranty_days'])
                    ->map(fn ($j) => [
                        'id' => $j->id,
                        'job_number' => $j->job_number,
                        'issue' => $j->issue,
                        'status' => $j->status,
                        'created_at' => $j->created_at->format('Y-m-d'),
                        'under_warranty' => $j->isUnderWarranty(),
                    ])
                : [],
            'statuses' => RepairJob::STATUSES,
            'technicians' => $this->technicians(),
            'shop' => [
                'name' => $settings->shop_name ?? config('app.name'),
                'phone' => $settings->shop_phone ?? '',
            ],
            'trackUrl' => route('track.index'),
        ]);
    }

    public function edit(RepairJob $repair)
    {
        $repair->load('customer:id,name,email,phone');

        return Inertia::render('Repairs/Form', $this->formOptions() + [
            'job' => $this->present($repair) + [
                'passcode' => $repair->passcode,
                'internal_notes' => $repair->internal_notes,
                'technician_id' => $repair->technician_id,
            ],
            'prefillCustomer' => null,
        ]);
    }

    public function update(Request $request, RepairJob $repair)
    {
        $repair->update($this->validateJob($request, $repair));

        return redirect()->route('repairs.show', $repair)->with('success', 'Repair job updated');
    }

    /** Lightweight inline edits from the job page (diagnosis, technician, notes…). */
    public function patch(Request $request, RepairJob $repair)
    {
        $data = $request->validate([
            'diagnosis' => 'sometimes|nullable|string',
            'internal_notes' => 'sometimes|nullable|string',
            'technician_id' => 'sometimes|nullable|exists:users,id',
            'priority' => 'sometimes|in:normal,urgent',
            'promised_at' => 'sometimes|nullable|date',
            'estimated_cost' => 'sometimes|numeric|min:0',
            'warranty_days' => 'sometimes|integer|min:0|max:3650',
        ]);

        $repair->update($data);

        if (array_key_exists('technician_id', $data)) {
            $repair->logs()->create([
                'user_id' => auth()->id(),
                'note' => 'Assigned to '.($repair->technician?->name ?? 'nobody'),
            ]);
        }

        return back()->with('success', 'Saved');
    }

    public function updateStatus(Request $request, RepairJob $repair)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(RepairJob::STATUSES))],
            'note' => 'nullable|string|max:2000',
            'customer_visible' => 'boolean',
        ]);

        if ($data['status'] === 'collected' && ! $repair->order_id) {
            return back()->with('error', 'Use "Checkout" to collect payment and hand the device back.');
        }

        $from = $repair->status;
        $updates = ['status' => $data['status']];
        if ($data['status'] === 'ready' && ! $repair->completed_at) {
            $updates['completed_at'] = now();
        }
        if ($data['status'] === 'in_progress' && $from === 'awaiting_approval' && ! $repair->approved_at) {
            $updates['approved_at'] = now();
        }
        if ($data['status'] === 'cancelled' && $from !== 'cancelled') {
            $updates['completed_at'] = now();
        }

        $repair->update($updates);
        $repair->logs()->create([
            'user_id' => auth()->id(),
            'from_status' => $from,
            'to_status' => $data['status'],
            'note' => $data['note'] ?? null,
            'customer_visible' => $data['customer_visible'] ?? true,
        ]);

        return back()->with('success', 'Status updated to '.RepairJob::STATUSES[$data['status']]);
    }

    public function addNote(Request $request, RepairJob $repair)
    {
        $data = $request->validate([
            'note' => 'required|string|max:2000',
            'customer_visible' => 'boolean',
        ]);

        $repair->logs()->create([
            'user_id' => auth()->id(),
            'note' => $data['note'],
            'customer_visible' => $data['customer_visible'] ?? false,
        ]);

        return back()->with('success', 'Note added');
    }

    public function addItem(Request $request, RepairJob $repair)
    {
        abort_if((bool) $repair->order_id, 422, 'This job has already been checked out.');

        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'name' => 'required_without:product_id|nullable|string|max:255',
            'item_type' => 'nullable|in:part,service,product',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'warranty_days' => 'nullable|integer|min:0|max:3650',
        ]);

        $product = ! empty($data['product_id']) ? Product::find($data['product_id']) : null;

        if ($product && $product->serial_tracked) {
            return back()->with('error', 'Serial-tracked items (e.g. devices) are sold at checkout, not as repair parts.');
        }

        $repair->items()->create([
            'product_id' => $product?->id,
            'name' => $product ? $product->name : $data['name'],
            'item_type' => $product ? $product->type : ($data['item_type'] ?? 'service'),
            'quantity' => $data['quantity'],
            'price' => $data['price'],
            'cost_price' => $product ? $product->cost_price : 0,
            'warranty_days' => $data['warranty_days'] ?? $product?->warranty_days,
        ]);

        return back()->with('success', 'Item added');
    }

    public function removeItem(RepairJob $repair, RepairJobItem $item)
    {
        abort_unless($item->repair_job_id === $repair->id, 404);
        abort_if((bool) $repair->order_id, 422, 'This job has already been checked out.');

        $item->delete();

        return back()->with('success', 'Item removed');
    }

    public function print(Request $request, RepairJob $repair)
    {
        $repair->load(['customer', 'receivedBy:id,name', 'technician:id,name', 'items']);

        return view('repairs.job-sheet', [
            'job' => $repair,
            'settings' => ShopSettings::first(),
            'format' => $request->input('format') === 'thermal' ? 'thermal' : 'a4',
            'checks' => RepairJob::PRE_CHECKS,
            'trackUrl' => route('track.index'),
        ]);
    }

    public function destroy(RepairJob $repair)
    {
        if ($repair->order_id) {
            return back()->with('error', 'A checked-out job cannot be deleted.');
        }

        $repair->delete();

        return redirect()->route('repairs.index')->with('success', "Repair job {$repair->job_number} deleted");
    }

    // ---------------------------------------------------------------------
    // Public customer tracking (no auth)
    // ---------------------------------------------------------------------

    public function trackForm()
    {
        return Inertia::render('Track', [
            'result' => null,
            'shop' => $this->publicShop(),
        ]);
    }

    public function track(Request $request)
    {
        $data = $request->validate([
            'job_number' => 'required|string|max:30',
            'phone' => 'required|string|max:30',
        ]);

        $job = RepairJob::with(['customer', 'logs' => fn ($q) => $q->where('customer_visible', true)])
            ->where('job_number', strtoupper(trim($data['job_number'])))
            ->first();

        // Verify ownership with the last 4 digits of the phone on file, so a job
        // number alone can't be used to snoop on someone else's repair.
        $given = substr(preg_replace('/\D+/', '', $data['phone']), -4);
        $onFile = substr(preg_replace('/\D+/', '', $job?->customer?->phone ?? ''), -4);

        if (! $job || strlen($given) < 4 || $given !== $onFile) {
            return back()->withErrors(['job_number' => 'No repair found for that job number and phone.']);
        }

        return Inertia::render('Track', [
            'shop' => $this->publicShop(),
            'result' => [
                'job_number' => $job->job_number,
                'device' => $job->device_label,
                'issue' => $job->issue,
                'status' => $job->status,
                'status_label' => $job->status_label,
                'statuses' => RepairJob::STATUSES,
                'quoted' => $job->quotedTotal(),
                'deposit' => (float) $job->deposit,
                'promised_at' => $job->promised_at?->format('d M Y, h:i A'),
                'received_at' => $job->created_at->format('d M Y, h:i A'),
                'warranty_expires_at' => $job->warrantyExpiresAt()?->format('d M Y'),
                'timeline' => $job->logs->sortByDesc('id')->map(fn ($l) => [
                    'status' => $l->to_status ? (RepairJob::STATUSES[$l->to_status] ?? $l->to_status) : null,
                    'note' => $l->note,
                    'at' => $l->created_at->format('d M Y, h:i A'),
                ])->values(),
            ],
        ]);
    }

    // ---------------------------------------------------------------------

    private function validateJob(Request $request, ?RepairJob $job = null): array
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'technician_id' => 'nullable|exists:users,id',
            'device_type' => ['required', Rule::in(array_keys(RepairJob::DEVICE_TYPES))],
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'imei' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'passcode_type' => 'required|in:none,pin,password,pattern',
            'passcode' => 'nullable|string|max:100',
            'accessories' => 'nullable|array',
            'accessories.*' => 'string|max:50',
            'pre_checks' => 'nullable|array',
            'pre_checks.*' => 'in:ok,faulty,na',
            'condition_notes' => 'nullable|string',
            'issue' => 'required|string',
            'diagnosis' => 'nullable|string',
            'priority' => 'required|in:normal,urgent',
            'estimated_cost' => 'nullable|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',
            'deposit_method' => 'nullable|in:cash,card,e-wallet,online_transfer',
            'warranty_days' => 'nullable|integer|min:0|max:3650',
            'promised_at' => 'nullable|date',
            'internal_notes' => 'nullable|string',
        ]);

        $data['estimated_cost'] = $data['estimated_cost'] ?? 0;
        $data['deposit'] = $data['deposit'] ?? 0;
        $data['warranty_days'] = $data['warranty_days'] ?? (int) (ShopSettings::first()->default_warranty_days ?? 30);
        $data['imei'] = isset($data['imei']) ? strtoupper(preg_replace('/\s+/', '', $data['imei'])) : null;
        if ($data['passcode_type'] === 'none') {
            $data['passcode'] = null;
        }
        if ($data['deposit'] <= 0) {
            $data['deposit_method'] = null;
        } elseif (empty($data['deposit_method'])) {
            $data['deposit_method'] = 'cash';
        }

        return $data;
    }

    private function formOptions(): array
    {
        $settings = ShopSettings::first();

        return [
            'deviceTypes' => RepairJob::DEVICE_TYPES,
            'accessoryOptions' => RepairJob::ACCESSORIES,
            'preCheckOptions' => RepairJob::PRE_CHECKS,
            'technicians' => $this->technicians(),
            'defaultWarrantyDays' => (int) ($settings->default_warranty_days ?? 30),
            'brandSuggestions' => RepairJob::query()->whereNotNull('brand')->distinct()->orderBy('brand')->limit(50)->pluck('brand')
                ->merge(['Apple', 'Samsung', 'Xiaomi', 'Oppo', 'Vivo', 'Huawei', 'Honor', 'Realme', 'Infinix', 'Google', 'OnePlus', 'Nokia'])
                ->unique()->values(),
        ];
    }

    private function technicians()
    {
        return User::query()->where('status', true)->orderBy('name')->get(['id', 'name']);
    }

    private function present(RepairJob $job): array
    {
        return [
            'id' => $job->id,
            'job_number' => $job->job_number,
            'customer' => $job->customer?->only(['id', 'name', 'email', 'phone']),
            'technician' => $job->technician?->name,
            'device_type' => $job->device_type,
            'device' => $job->device_label,
            'brand' => $job->brand,
            'model' => $job->model,
            'imei' => $job->imei,
            'color' => $job->color,
            'passcode_type' => $job->passcode_type,
            'accessories' => $job->accessories ?? [],
            'pre_checks' => $job->pre_checks ?? (object) [],
            'condition_notes' => $job->condition_notes,
            'issue' => $job->issue,
            'diagnosis' => $job->diagnosis,
            'status' => $job->status,
            'priority' => $job->priority,
            'estimated_cost' => (float) $job->estimated_cost,
            'deposit' => (float) $job->deposit,
            'deposit_method' => $job->deposit_method,
            'warranty_days' => $job->warranty_days,
            'quoted_total' => $job->relationLoaded('items') ? $job->quotedTotal() : (float) $job->estimated_cost,
            'promised_at' => $job->promised_at?->format('Y-m-d\TH:i'),
            'approved_at' => $job->approved_at?->format('Y-m-d H:i'),
            'completed_at' => $job->completed_at?->format('Y-m-d H:i'),
            'collected_at' => $job->collected_at?->format('Y-m-d H:i'),
            'created_at' => $job->created_at->format('Y-m-d H:i'),
            'order_id' => $job->order_id,
        ];
    }

    private function publicShop(): array
    {
        $settings = ShopSettings::first();

        return [
            'name' => $settings->shop_name ?? config('app.name'),
            'phone' => $settings->shop_phone ?? '',
            'address' => $settings->shop_address ?? '',
        ];
    }
}
