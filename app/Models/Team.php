<?php

namespace App\Models;

use App\Enums\ShiftEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'shift',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift' => ShiftEnum::class,
        ];
    }

    /**
     * Get the supporters associated with the team.
     * * @return HasMany
     */
    public function supporters(): HasMany
    {
        return $this->hasMany(User::class, 'team_id');
    }
}