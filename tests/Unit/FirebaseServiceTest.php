<?php

namespace Tests\Unit;

use App\Models\FcmToken;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if service attempts to send an HTTP request when user has tokens.
     * Note: This assumes Google Client works, testing HTTP facade directly.
     *
     * @return void
     */
    public function test_service_sends_notification_if_tokens_exist(): void
    {
        Http::fake(); // Prevent actual outgoing requests

        $user = User::factory()->create();
        FcmToken::create([
            'user_id' => $user->id,
            'token' => 'fake_token',
            'device_type' => 'android'
        ]);

        // Mock the service to bypass the actual OAuth2 credential fetching which fails without the json file
        $mock = $this->partialMock(FirebaseService::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()
                 ->shouldReceive('getAccessToken')
                 ->andReturn('fake_access_token');
        });

        // Use the mocked instance
        $mock->sendNotificationToUser($user, 'Test Title', 'Test Body', ['ticket_id' => '1']);

        // Assert HTTP POST was initiated to FCM endpoint
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com');
        });
    }
}