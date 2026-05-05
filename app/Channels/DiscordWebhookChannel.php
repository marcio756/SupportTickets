<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use App\Models\DiscordSetting;

/**
 * Custom notification channel responsible for dispatching payloads to a Discord Channel via Bot API.
 */
class DiscordWebhookChannel
{
    /**
     * Transmits the given notification payload to the external Discord service.
     *
     * @param object $notifiable The entity receiving the notification.
     * @param Notification $notification The notification instance containing the payload formatting.
     * @return void
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toDiscord')) {
            return;
        }

        $botToken = config('services.discord.bot_token');
        
        // Fetch dynamically from database
        $setting = DiscordSetting::where('key', 'ticket_channel_id')->first();
        $channelId = $setting ? $setting->value : null;

        if (empty($botToken) || empty($channelId)) {
            return;
        }

        $data = $notification->toDiscord($notifiable);
        $url = "https://discord.com/api/v10/channels/{$channelId}/messages";

        Http::timeout(3)->withHeaders([
            'Authorization' => "Bot {$botToken}"
        ])->post($url, $data);
    }
}