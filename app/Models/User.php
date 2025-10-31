<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\LogsActivity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'tenant_id',
        'is_active',
        'locale',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user can access the Filament panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Only allow access if user is active
        if (! $this->is_active) {
            return false;
        }

        // For the admin panel (used for unified login)
        // Allow all users to authenticate, LoginResponse handles redirection
        if ($panel->getId() === 'admin') {
            return true; // Allow all active users (they'll be redirected by LoginResponse)
        }

        if ($panel->getId() === 'panel') {
            return in_array($this->role, ['subadmin', 'employee']);
        }

        if ($panel->getId() === 'portal') {
            return $this->role === 'customer';
        }

        return false;
    }

    /**
     * Relationship: User belongs to a tenant (sub-admin)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Relationship: Sub-admin has many employees
     */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id')->where('role', 'employee');
    }

    /**
     * Relationship: All tenant users (for backwards compatibility)
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * Scope: Get only sub-admins
     */
    public function scopeSubAdmins($query)
    {
        return $query->where('role', 'subadmin');
    }

    /**
     * Scope: Get only employees
     */
    public function scopeEmployees($query)
    {
        return $query->where('role', 'employee');
    }

    /**
     * Scope: Get only customers
     */
    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }

    /**
     * Scope: Get users for a specific tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Check if user is a superadmin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * Check if user is a sub-admin
     */
    public function isSubAdmin(): bool
    {
        return $this->role === 'subadmin';
    }

    /**
     * Check if user is an employee
     */
    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /**
     * Check if user is a customer
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }
}
