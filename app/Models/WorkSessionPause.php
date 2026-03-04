<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSessionPause extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_session_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * The parent work session.
     *
     * @return BelongsTo
     */
    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }
}