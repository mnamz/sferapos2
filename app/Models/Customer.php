<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'state_code',
        'country',
        'status',
        'tin',
        'brn',
        'nric',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function repairJobs()
    {
        return $this->hasMany(RepairJob::class);
    }

    /**
     * Normalise a Malaysian-style phone number to E.164 (+60…), which is what
     * MyInvois and WhatsApp links expect. Counter staff type "012-345 6789".
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with(trim($phone), '+')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '60')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+6'.$digits;
        }

        return '+60'.$digits;
    }
} 