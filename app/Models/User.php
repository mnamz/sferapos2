<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Models\Audit;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, AuditableTrait;

    public const INACTIVE_MESSAGE = 'This account is inactive. Ask a manager to reactivate it.';

    // Lets a staff account (e.g. a store supervisor) open the Sales Register
    public const PERMISSION_VIEW_SALES_REGISTER = 'view-sales-register';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean'
        ];
    }

    /**
     * Inactive users can't log in. A null status (unsaved model, column
     * default not yet loaded) counts as active, matching the DB default.
     */
    public function isActive(): bool
    {
        return $this->status !== false;
    }

    public function auditEvent($event)
    {
        // Create an audit entry with a custom event (e.g., login, logout)
        Audit::create([
            'auditable_type' => self::class,
            'auditable_id'   => $this->id,
            'event'          => $event,
            'url'            => request()->fullUrl(),
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
            'created_at'     => now(),
        ]);
    }
}
