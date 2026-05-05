<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Manages dynamic configuration settings for the Discord Bot integration.
 */
class DiscordSetting extends Model
{
    protected $table = 'discord_settings';
    
    protected $primaryKey = 'key';
    
    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];
}