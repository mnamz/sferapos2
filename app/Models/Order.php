<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Order extends Model implements Auditable
{
    use HasFactory, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'customer_id',
        'user_id',
        'order_number',
        'subtotal',
        'tax',
        'delivery_cost',
        'discount',
        'total',
        'profit',
        'paid_amount',
        'due_amount',
        'change_amount',
        'payment_method',
        'delivery_method',
        'remarks',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'profit' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    // Define the available delivery methods
    public static $deliveryMethods = [
        'pickup' => 'Pickup',
        'delivery' => 'Delivery',
    ];

    // Define the available payment methods
    public static $paymentMethods = [
        'cash' => 'Cash',
        'card' => 'Card',
        'bank_transfer' => 'Bank Transfer',
    ];

    // Define the available statuses
    public static $statuses = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Calculate due amount based on total and paid amount
    public function calculateDueAmount()
    {
        return $this->total + $this->delivery_cost - $this->paid_amount;
    }
} 