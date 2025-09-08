<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type', // income | expense
        'subtype', // general | payroll | cogs
        'description',
    ];

    public function entries()
    {
        return $this->hasMany(AccountingEntry::class, 'category_id');
    }
}


