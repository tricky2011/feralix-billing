<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'technician_id',
        'dashboard_active_router_id',
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
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'technician_id' => 'integer',
            'dashboard_active_router_id' => 'integer',
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function dashboardActiveRouter(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'dashboard_active_router_id');
    }

    public function accessibleRouters(): BelongsToMany
    {
        return $this->belongsToMany(Router::class, 'user_router_assignments')
            ->withTimestamps()
            ->orderBy('router_name');
    }

    public function routers(): BelongsToMany
    {
        return $this->belongsToMany(Router::class, 'user_router')
            ->withTimestamps()
            ->orderBy('router_name');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function paymentsCreated(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function serviceIsolationsCreated(): HasMany
    {
        return $this->hasMany(ServiceIsolation::class, 'created_by');
    }

    public function businessExpensesCreated(): HasMany
    {
        return $this->hasMany(BusinessExpense::class, 'created_by');
    }

    public function isSuperadmin(): bool
    {
        return $this->role === UserRole::Superadmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isTechnician(): bool
    {
        return $this->role === UserRole::Technician;
    }

    public function isReseller(): bool
    {
        return $this->role === UserRole::Reseller;
    }
}
