<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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

    /**
     * Check if the user is a supporter.
     *
     * @return bool
     */
    public function isSupporter(): bool
    {
        return $this->role === RoleEnum::SUPPORTER;
    }

    /**
     * Check if the user is a customer.
     *
     * @return bool
     */
    public function isCustomer(): bool
    {
        return $this->role === RoleEnum::CUSTOMER;
    }

    /**
     * Retrieves all tickets created by this user (if acting as a customer).
     *
     * @return HasMany
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'customer_id');
    }

    /**
     * Retrieves all tickets assigned to this user (if acting as a supporter).
     *
     * @return HasMany
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }
}