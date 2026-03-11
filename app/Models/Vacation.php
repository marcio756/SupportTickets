<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vacation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'supporter_id',
        'start_date',
        'end_date',
        'total_days',
        'year',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'integer',
            'year' => 'integer',
        ];
    }

    /**
     * Get the supporter that owns the vacation.
     * * @return BelongsTo
     */
    public function supporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supporter_id');
    }
}