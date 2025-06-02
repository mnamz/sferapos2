<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use OwenIt\Auditing\Models\Audit;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->startOfMonth();
            
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $query = Audit::with(['user'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->when($request->input('user_id'), function($query, $userId) {
                $query->where('user_id', $userId);
            })
            ->when($request->input('event'), function($query, $event) {
                $query->where('event', $event);
            })
            ->when($request->input('auditable_type'), function($query, $type) {
                $query->where('auditable_type', 'App\\Models\\' . $type);
            });

        $audits = $query->latest()
            ->paginate(10)
            ->through(function ($audit) {
                return [
                    'id' => $audit->id,
                    'user' => $audit->user ? [
                        'id' => $audit->user->id,
                        'name' => $audit->user->name,
                    ] : null,
                    'event' => $audit->event,
                    'auditable_type' => class_basename($audit->auditable_type),
                    'auditable_id' => $audit->auditable_id,
                    'old_values' => $audit->old_values,
                    'new_values' => $audit->new_values,
                    'url' => $audit->url,
                    'ip_address' => $audit->ip_address,
                    'user_agent' => $audit->user_agent,
                    'created_at' => Carbon::parse($audit->created_at)->format('Y-m-d H:i:s'),
                ];
            });

        return Inertia::render('ActivityLog/Index', [
            'audits' => $audits,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'user_id' => $request->input('user_id'),
                'event' => $request->input('event'),
                'auditable_type' => $request->input('auditable_type'),
            ],
        ]);
    }
} 