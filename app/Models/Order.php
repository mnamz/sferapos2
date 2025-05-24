<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'user_id',
        'subtotal',
        'tax',
        'total',
        'paid_amount',
        'due_amount',
        'change_amount',
        'delivery_cost',
        'delivery_method',
        'payment_method',
        'remarks',
        'status',
        'profit',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
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