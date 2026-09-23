<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class RepairJob extends Model implements Auditable
{
    use AuditableTrait, SoftDeletes;

    public const STATUSES = [
        'received' => 'Received',
        'diagnosing' => 'Diagnosing',
        'awaiting_approval' => 'Awaiting Approval',
        'awaiting_parts' => 'Awaiting Parts',
        'in_progress' => 'In Progress',
        'ready' => 'Ready for Pickup',
        'collected' => 'Collected',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses that still occupy the bench (not yet handed back). */
    public const OPEN_STATUSES = ['received', 'diagnosing', 'awaiting_approval', 'awaiting_parts', 'in_progress', 'ready'];

    public const DEVICE_TYPES = [
        'phone' => 'Phone',
        'tablet' => 'Tablet',
        'laptop' => 'Laptop',
        'watch' => 'Smartwatch',
        'earbuds' => 'Earbuds',
        'console' => 'Console',
        'other' => 'Other',
    ];

    public const ACCESSORIES = ['SIM card', 'SIM tray', 'Memory card', 'Case / cover', 'Charger', 'Cable', 'Box', 'Stylus'];

    public const PRE_CHECKS = [
        'power' => 'Powers on',
        'display' => 'Display',
        'touch' => 'Touch',
        'front_camera' => 'Front camera',
        'rear_camera' => 'Rear camera',
        'speaker' => 'Speaker',
        'microphone' => 'Microphone',
        'charging' => 'Charging',
        'buttons' => 'Buttons',
        'wifi' => 'Wi-Fi / Bluetooth',
        'signal' => 'Cellular signal',
        'biometrics' => 'Face ID / Fingerprint',
    ];

    protected $fillable = [
        'job_number',
        'customer_id',
        'user_id',
        'technician_id',
        'device_type',
        'brand',
        'model',
        'imei',
        'color',
        'passcode_type',
        'passcode',
        'accessories',
        'pre_checks',
        'condition_notes',
        'issue',
        'diagnosis',
        'status',
        'priority',
        'estimated_cost',
        'deposit',
        'deposit_method',
        'warranty_days',
        'promised_at',
        'approved_at',
        'completed_at',
        'collected_at',
        'internal_notes',
        'order_id',
    ];

    protected $casts = [
        'accessories' => 'array',
        'pre_checks' => 'array',
        'estimated_cost' => 'decimal:2',
        'deposit' => 'decimal:2',
        'warranty_days' => 'integer',
        'promised_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    // The device passcode is sensitive: never leak it into the audit trail.
    protected $auditExclude = ['passcode'];

    public static function nextJobNumber(): string
    {
        $prefix = 'RJ'.now()->format('ym').'-';
        $last = static::withTrashed()->where('job_number', 'like', $prefix.'%')->orderByDesc('id')->value('job_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RepairJobItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RepairJobLog::class)->latest('id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function getDeviceLabelAttribute(): string
    {
        return trim(($this->brand ?? '').' '.($this->model ?? '')) ?: (self::DEVICE_TYPES[$this->device_type] ?? 'Device');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function itemsTotal(): float
    {
        return (float) $this->items->sum(fn ($i) => $i->price * $i->quantity);
    }

    /** The amount to quote: actual line items once added, else the intake estimate. */
    public function quotedTotal(): float
    {
        return $this->items->isNotEmpty() ? $this->itemsTotal() : (float) $this->estimated_cost;
    }

    public function warrantyExpiresAt(): ?\Carbon\Carbon
    {
        if (! $this->collected_at || ! $this->warranty_days) {
            return null;
        }

        return $this->collected_at->copy()->addDays($this->warranty_days);
    }

    public function isUnderWarranty(): bool
    {
        $expires = $this->warrantyExpiresAt();

        return $expires !== null && $expires->isFuture();
    }
}
