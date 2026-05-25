<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'team_id',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'has_two_factor_enabled',
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
            // Architect Note: role enum cast removed to prevent ValueError 500 crashes
            // from un-sanitized or legacy string data in the database during hydration.
            'daily_support_seconds' => 'integer',
        ];
    }

    // --- Accessors for API Resources ---

    /**
     * Determine if the user has Two-Factor Authentication enabled.
     */
    public function getHasTwoFactorEnabledAttribute(): bool
    {
        return !empty($this->two_factor_secret);
    }

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

    /**
     * Checks if the user is a system administrator.
     * * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::ADMIN || $this->role === RoleEnum::ADMIN->value || $this->role === 'admin';
    }

    /**
     * Checks if the user is a support agent.
     * * @return bool
     */
    public function isSupporter(): bool
    {
        return $this->role === RoleEnum::SUPPORTER || $this->role === RoleEnum::SUPPORTER->value || $this->role === 'supporter';
    }

    /**
     * Checks if the user is a customer.
     * * @return bool
     */
    public function isCustomer(): bool
    {
        return $this->role === RoleEnum::CUSTOMER || $this->role === RoleEnum::CUSTOMER->value || $this->role === 'customer';
    }

    /**
     * Checks if the user belongs to the internal staff (either an Admin or a Supporter).
     * Centralizes logic to prevent code duplication across controllers.
     * * @return bool
     */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isSupporter();
    }

    /**
     * Determines if the user is an authorized system developer.
     * Prevents business administrators from accessing deep infrastructure metrics.
     * * @return bool
     */
    public function isDeveloper(): bool
    {
        $developerEmails = [
            'developer@supporttickets.com',
            'teu.email@exemplo.com',
        ];

        return in_array($this->email, $developerEmails, true);
    }

    // --- Relationships ---

    /**
     * Get the team the user belongs to.
     * * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the vacations booked by the supporter.
     * * @return HasMany
     */
    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class, 'supporter_id');
    }

    /**
     * Get the tickets created by the customer.
     * * @return HasMany
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'customer_id');
    }

    /**
     * Get the tickets assigned to the support agent.
     * * @return HasMany
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /**
     * Get the work sessions logged by the user.
     * * @return HasMany
     */
    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    /**
     * Get the push notification tokens for the user.
     * * @return HasMany
     */
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }
}