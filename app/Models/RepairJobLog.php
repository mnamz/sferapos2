<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairJobLog extends Model
{
    protected $fillable = [
        'repair_job_id',
        'user_id',
        'from_status',
        'to_status',
        'note',
        'customer_visible',
    ];

    protected $casts = [
        'customer_visible' => 'boolean',
    ];

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
