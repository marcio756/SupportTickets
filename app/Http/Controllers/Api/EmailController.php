<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\JsonResponse;

/**
 * Controller responsible for managing email-related operations via API.
 */
class EmailController extends Controller
{
    use ApiResponser;

    /**
     * Triggers the artisan command to fetch emails from the IMAP server.
     * Enqueues the command to background processing to ensure low latency HTTP response.
     *
     * @return JsonResponse
     */
    public function fetch(): JsonResponse
    {
        if (!auth()->user()->isSupporter()) {
            return $this->errorResponse('Unauthorized access. Only supporters can sync emails.', 403);
        }

        try {
            // Dispatches the console command to a background queue instead of running synchronously
            Artisan::queue('app:fetch-support-emails')->onQueue('emails');

            return $this->successResponse(
                null,
                'Emails sync triggered successfully. Processing in background.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to trigger email sync: ' . $e->getMessage(), 500);
        }
    }
}