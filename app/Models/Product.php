<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Product extends Model implements Auditable
{
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'cost_price',
        'stock',
        'serial_tracked',
        'pending_serial_count',
        'category_id',
        'image',
        'status',
        'barcode',
        'supplier_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'status' => 'boolean',
        'serial_tracked' => 'boolean',
        'pending_serial_count' => 'integer',
    ];

    protected $appends = ['image_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function serials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function getImageUrlAttribute()
    {
        if (! $this->image) {
            return null;
        }

        return Storage::disk('public')->url($this->image);
    }

    /**
     * Scope a query to match a product name using case-insensitive, normalized comparison.
     * Normalization removes '/', '-', and '*' characters from both the column and the query.
     */
    public function scopeWhereNameMatchesNormalized($query, string $term)
    {
        $normalizedTerm = strtolower(str_replace(['/', '-', '*', ' '], '', $term));

        // Use nested REPLACE to normalize the name column; wrap in LOWER for case-insensitive match
        $raw = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(products.name, '/', ''), '-', ''), '*', ''), ' ', '')) LIKE ?";

        return $query->whereRaw($raw, ["%{$normalizedTerm}%"]);
    }
}
