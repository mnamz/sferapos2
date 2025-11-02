<?php

namespace App\Http\Controllers;

use App\Models\MyInvoisInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyInvoisInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = MyInvoisInvoice::with(['order.customer', 'order.user'])
            ->orderBy('created_at', 'desc');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_code_number', 'like', "%{$search}%")
                  ->orWhere('submission_uid', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($orderQuery) use ($search) {
                      $orderQuery->where('order_number', 'like', "%{$search}%")
                                 ->orWhere('id', $search);
                  });
            });
        }

        $invoices = $query->paginate(50);

        return Inertia::render('MyInvois/ConsolidatedInvoices', [
            'invoices' => $invoices->through(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'order_id' => $invoice->order_id,
                    'order_number' => $invoice->order->order_number ?? null,
                    'order_date' => $invoice->order->created_at->format('Y-m-d H:i:s') ?? null,
                    'customer_name' => $invoice->order->customer->name ?? 'Walk-in',
                    'customer_email' => $invoice->order->customer->email ?? null,
                    'order_total' => number_format($invoice->order->total ?? 0, 2),
                    'submission_uid' => $invoice->submission_uid,
                    'uuid' => $invoice->uuid,
                    'invoice_code_number' => $invoice->invoice_code_number,
                    'pushed_at' => $invoice->created_at->format('Y-m-d H:i:s'),
                    'cashier' => $invoice->order->user->name ?? null,
                ];
            }),
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(MyInvoisInvoice $myInvoisInvoice)
    {
        $myInvoisInvoice->load(['order.customer', 'order.user', 'order.items', 'order.expenses']);

        return Inertia::render('MyInvois/InvoiceDetails', [
            'invoice' => [
                'id' => $myInvoisInvoice->id,
                'order_id' => $myInvoisInvoice->order_id,
                'submission_uid' => $myInvoisInvoice->submission_uid,
                'uuid' => $myInvoisInvoice->uuid,
                'invoice_code_number' => $myInvoisInvoice->invoice_code_number,
                'request_payload' => $myInvoisInvoice->request_payload,
                'response_payload' => $myInvoisInvoice->response_payload,
                'created_at' => $myInvoisInvoice->created_at->format('Y-m-d H:i:s'),
                'order' => [
                    'id' => $myInvoisInvoice->order->id,
                    'order_number' => $myInvoisInvoice->order->order_number,
                    'customer' => $myInvoisInvoice->order->customer ? [
                        'name' => $myInvoisInvoice->order->customer->name,
                        'email' => $myInvoisInvoice->order->customer->email,
                        'phone' => $myInvoisInvoice->order->customer->phone,
                        'address' => $myInvoisInvoice->order->customer->address,
                    ] : null,
                    'cashier' => [
                        'name' => $myInvoisInvoice->order->user->name,
                    ],
                    'total' => number_format($myInvoisInvoice->order->total, 2),
                    'subtotal' => number_format($myInvoisInvoice->order->subtotal, 2),
                    'tax' => number_format($myInvoisInvoice->order->tax, 2),
                    'discount' => number_format($myInvoisInvoice->order->discount, 2),
                    'items' => $myInvoisInvoice->order->items->map(function ($item) {
                        return [
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'price' => number_format($item->price, 2),
                            'total' => number_format($item->total, 2),
                        ];
                    }),
                    'created_at' => $myInvoisInvoice->order->created_at->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }
}

