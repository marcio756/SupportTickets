<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Illuminate\Support\Facades\Http;
use App\Models\DiscordSetting;

/**
 * Custom Monolog handler to route application error logs to a Discord Channel via Bot API.
 */
class DiscordHandler extends AbstractProcessingHandler
{
    /**
     * Formats and transmits the log record payload to the configured Discord channel using the Bot Token.
     *
     * @param LogRecord $record The localized log event data containing the error details.
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $botToken = config('services.discord.bot_token');
        
        // Fetch dynamically from database
        $setting = DiscordSetting::where('key', 'error_channel_id')->first();
        $channelId = $setting ? $setting->value : null;

        if (empty($botToken) || empty($channelId)) {
            return;
        }

        $content = sprintf(
            "🚨 **[%s] %s**\n```\n%s\n
```",
            $record->level->name,
            config('app.env'),
            substr($record->message, 0, 1500)
        );

        $url = "https://discord.com/api/v10/channels/{$channelId}/messages";

        Http::timeout(3)->withHeaders([
            'Authorization' => "Bot {$botToken}"
        ])->post($url, [
            'content' => $content,
        ]);
    }
}