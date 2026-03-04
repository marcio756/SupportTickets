<?php

namespace App\Models;

use App\Enums\WorkSessionStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'started_at',
        'ended_at',
        'total_worked_seconds',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkSessionStatusEnum::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_worked_seconds' => 'integer',
        ];
    }

    /**
     * The supporter this session belongs to.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All pause intervals recorded within this session.
     *
     * @return HasMany
     */
    public function pauses(): HasMany
    {
        return $this->hasMany(WorkSessionPause::class);
    }
}