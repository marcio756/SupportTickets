<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'message',
        'target_audience',
        'recipient_ids',
    ];

    protected $casts = [
        'recipient_ids' => 'array',
    ];
}