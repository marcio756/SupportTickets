<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/**
 * Discord Webhook Notification Channel
 *
 * Handles the transmission of notifications to Discord via Webhooks.
 * This isolates the HTTP protocol logic from the notification payload classes,
 * adhering strictly to the Single Responsibility Principle (SRP).
 */
class DiscordWebhookChannel
{
    /**
     * Send the given notification via HTTP POST to Discord.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toDiscord')) {
            return;
        }

        $message = $notification->toDiscord($notifiable);
        $url = config('services.discord.tickets_webhook_url');

        if (! $url) {
            return;
        }

        // We use Optimistic UI/Fire-and-forget principles here. 
        // A timeout is set to ensure the app doesn't hang if Discord API is slow.
        Http::timeout(3)->post($url, $message);
    }
}