<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Handles incoming interactions (Slash Commands) from Discord.
 * Validates the ED25519 signature to ensure authenticity.
 */
class DiscordInteractionController extends Controller
{
    /**
     * Processes the Discord interaction webhook.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Verify Discord Signature (Mandatory for Discord Interactions)
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid request signature'], 401);
        }

        $type = $request->input('type');

        // 2. Handle PING from Discord (Type 1)
        if ($type === 1) {
            return response()->json(['type' => 1]);
        }

        // 3. Handle Application Commands (Type 2)
        if ($type === 2) {
            $commandName = $request->input('data.name');

            if ($commandName === 'setchannel') {
                return $this->handleSetChannelCommand($request);
            }
        }

        return response()->json(['error' => 'Unknown command'], 400);
    }

    /**
     * Handles the /setchannel logic, storing the selected channel in the database.
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleSetChannelCommand(Request $request): JsonResponse
    {
        $options = $request->input('data.options');
        $module = null;
        $channelId = null;

        foreach ($options as $option) {
            if ($option['name'] === 'modulo') {
                $module = $option['value']; // 'tickets' or 'errors'
            }
            if ($option['name'] === 'canal') {
                $channelId = $option['value'];
            }
        }

        if (! $module || ! $channelId) {
            return response()->json([
                'type' => 4, // ChannelMessageWithSource
                'data' => [
                    'content' => '❌ Faltam parâmetros no comando.'
                ]
            ]);
        }

        $settingKey = $module === 'tickets' ? 'ticket_channel_id' : 'error_channel_id';

        DiscordSetting::updateOrCreate(
            ['key' => $settingKey],
            ['value' => $channelId]
        );

        $moduleName = $module === 'tickets' ? 'Tickets' : 'Erros de Sistema';

        return response()->json([
            'type' => 4,
            'data' => [
                'content' => "✅ Sucesso! O módulo de **{$moduleName}** enviará mensagens para o canal <#{$channelId}> a partir de agora."
            ]
        ]);
    }

    /**
     * Handles requests from the Discord bot (Node.js) to set channel configuration.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setChannel(Request $request): JsonResponse
    {
        $modulo = $request->input('modulo');
        $channelId = $request->input('channel_id');

        if (! $modulo || ! $channelId) {
            return response()->json([
                'success' => false,
                'message' => 'Parâmetros faltando'
            ], 400);
        }

        $settingKey = $modulo === 'tickets' ? 'ticket_channel_id' : 'error_channel_id';

        DiscordSetting::updateOrCreate(
            ['key' => $settingKey],
            ['value' => $channelId]
        );

        return response()->json([
            'success' => true,
            'message' => 'Canal configurado com sucesso'
        ]);
    }

    /**
     * Verifies the ED25519 signature from Discord.
     *
     * @param Request $request
     * @return bool
     */
    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $body = $request->getContent();
        $publicKey = config('services.discord.public_key');

        if (! $signature || ! $timestamp || ! $publicKey) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached(
                hex2bin($signature),
                $timestamp . $body,
                hex2bin($publicKey)
            );
        } catch (\Exception $e) {
            return false;
        }
    }
}