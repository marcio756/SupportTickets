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
            // Architect Note: status enum cast removed to prevent ValueError 500
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_worked_seconds' => 'integer',
        ];
    }

    /**
     * By appending this, the JSON response will always include the dynamically 
     * calculated 'total_duration_seconds' so the Flutter app can resume its timer accurately.
     */
    protected $appends = ['total_duration_seconds'];

    /**
     * Calculates the real-time active duration dynamically.
     *
     * @return int
     */
    public function getTotalDurationSecondsAttribute(): int
    {
        $statusValue = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        // If it's finished, simply return the database recorded total.
        if ($statusValue === WorkSessionStatusEnum::COMPLETED->value) {
            return (int) ($this->total_worked_seconds ?? 0);
        }

        // Architect Note: Guard against calling diffInSeconds on null which crashes with 500
        if (empty($this->started_at)) {
            return 0;
        }

        $now = now();
        $pauses = $this->pauses ?? collect(); 
        
        $totalPauseSeconds = $pauses->sum(function ($pause) use ($now) {
            if (empty($pause->started_at)) return 0;
            
            $end = $pause->ended_at ?? $now;
            return $pause->started_at->diffInSeconds($end);
        });

        $grossSeconds = $this->started_at->diffInSeconds($now);
        return max(0, $grossSeconds - $totalPauseSeconds);
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