<?php

namespace App\Models;

use App\Enums\WorkSessionStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_worked_seconds' => 'integer',
        ];
    }

    /**
     * Appends calculated duration for real-time tracking.
     */
    protected $appends = ['total_duration_seconds'];

    /**
     * Calculates the real-time active duration dynamically.
     * Hardened against serialization failures and missing Log facade.
     *
     * @return int
     */
    public function getTotalDurationSecondsAttribute(): int
    {
        try {
            // Defensive check for status to handle both enums and strings safely
            $statusValue = $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status;

            if ($statusValue === WorkSessionStatusEnum::COMPLETED->value) {
                return (int) ($this->total_worked_seconds ?? 0);
            }

            if (!$this->started_at) {
                return 0;
            }

            $now = now();
            $pauses = $this->relationLoaded('pauses') ? $this->pauses : $this->pauses()->get(); 
            
            $totalPauseSeconds = 0;
            foreach ($pauses as $pause) {
                if (!$pause->started_at) continue;
                
                $start = Carbon::parse($pause->started_at);
                $end = $pause->ended_at ? Carbon::parse($pause->ended_at) : $now;
                $totalPauseSeconds += $start->diffInSeconds($end);
            }

            $startSession = Carbon::parse($this->started_at);
            $grossSeconds = $startSession->diffInSeconds($now);
            
            return (int) max(0, $grossSeconds - $totalPauseSeconds);

        } catch (\Throwable $e) {
            // Use global helper to avoid class-not-found issues during critical serialization
            logger()->error("Fail-safe triggered in WorkSession model (ID: {$this->id}): " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Relationship: Supporter/Staff who owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Recorded pauses within this session.
     */
    public function pauses(): HasMany
    {
        return $this->hasMany(WorkSessionPause::class);
    }
}