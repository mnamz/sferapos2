<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency marker: this branch has applied {leg} of {transfer_number} to its
 * own inventory. Prevents a re-apply when a confirm fails and HQ re-serves the
 * same leg on the next poll.
 */
class HqTransferLeg extends Model
{
    protected $fillable = [
        'transfer_number',
        'leg',
        'applied_at',
        'confirmed_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];
}
