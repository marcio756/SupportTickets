<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'daily_support_seconds',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'role' => RoleEnum::class,
            'daily_support_seconds' => 'integer',
        ];
    }

    // --- Accessors for API Resources ---

    /**
     * Total chat time limit in seconds (formatted for Resource).
     */
    public function getMaxChatTimeAttribute(): int
    {
        return 1800; // 30 minutes daily limit
    }

    /**
     * Remaining chat time in seconds.
     */
    public function getRemainingChatTimeAttribute(): int
    {
        return $this->daily_support_seconds ?? 0;
    }

    // --- Role Checks ---

    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::ADMIN || $this->role === RoleEnum::ADMIN->value;
    }

    public function isSupporter(): bool
    {
        return $this->role === RoleEnum::SUPPORTER || $this->role === RoleEnum::SUPPORTER->value;
    }

    public function isCustomer(): bool
    {
        return $this->role === RoleEnum::CUSTOMER || $this->role === RoleEnum::CUSTOMER->value;
    }

    // --- Relationships ---

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'customer_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }
}