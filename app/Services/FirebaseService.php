<?php

namespace App\Services;

use App\Models\User;
use Google\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for isolating all Firebase Cloud Messaging (FCM) logic.
 * It uses the Google Auth Library to generate short-lived OAuth2 tokens for the FCM HTTP v1 API.
 */
class FirebaseService
{
    /**
     * Send a push notification to all registered devices of a specific user.
     *
     * @param User $user The recipient user.
     * @param string $title The notification title.
     * @param string $body The notification body message.
     * @param array $data Additional optional data payload to be processed by the apps.
     * @return void
     */
    public function sendNotificationToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = $user->fcmTokens()->pluck('token')->toArray();

        if (empty($tokens)) {
            return;
        }

        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            Log::error('FirebaseService: Failed to retrieve Google OAuth2 access token.');
            return;
        }

        $projectId = env('FIREBASE_PROJECT_ID'); // Can also be abstracted to config('services.firebase.project_id')

        if (!$projectId) {
            Log::error('FirebaseService: FIREBASE_PROJECT_ID is not defined in the environment variables.');
            return;
        }

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => empty($data) ? null : $data,
                    ],
                ]);

            if ($response->failed()) {
                Log::error("FirebaseService: Failed to send push to token {$token}.", ['error' => $response->json()]);
                
                // If the token is invalid or unregistered, we should clean it up from the database
                $errorCode = $response->json('error.details.0.errorCode');
                if ($errorCode === 'UNREGISTERED') {
                    $user->fcmTokens()->where('token', $token)->delete();
                }
            }
        }
    }

    /**
     * Generate an OAuth2 Access Token using the service account credentials.
     *
     * @return string|null
     */
    private function getAccessToken(): ?string
    {
        try {
            $credentialsPath = storage_path('app/firebase-credentials.json');
            
            if (!file_exists($credentialsPath)) {
                Log::error("FirebaseService: Credentials file not found at {$credentialsPath}. Please add your Firebase service account JSON file.");
                return null;
            }

            $client = new Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->fetchAccessTokenWithAssertion();
            
            $token = $client->getAccessToken();
            
            return $token['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error("FirebaseService: Exception while getting access token - " . $e->getMessage());
            return null;
        }
    }
}