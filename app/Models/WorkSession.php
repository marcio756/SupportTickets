<?php

namespace App\Models;

use App\Enums\WorkSessionStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'status' => WorkSessionStatusEnum::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_worked_seconds' => 'integer',
        ];
    }

    protected $appends = ['total_duration_seconds'];

    public function getTotalDurationSecondsAttribute(): int
    {
        try {
            $statusValue = $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status;

            if ($statusValue === WorkSessionStatusEnum::COMPLETED->value) {
                return (int) ($this->total_worked_seconds ?? 0);
            }

            if (!$this->started_at) {
                return 0;
            }

            $now = now();
            // Arquitetura: Prevenção extra para evitar pesquisas N+1 descontroladas na Base de Dados
            $pauses = $this->relationLoaded('pauses') ? $this->pauses : collect(); 
            
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
            try { logger()->error("Falha no WorkSession model: " . $e->getMessage()); } catch (\Throwable $log) {}
            return 0;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pauses(): HasMany
    {
        return $this->hasMany(WorkSessionPause::class);
    }
}