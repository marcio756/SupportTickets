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
     * Useful for manual "Pull to Refresh" actions in the mobile app.
     * * Note: In a high-traffic environment, this should ideally dispatch a Job
     * rather than running synchronously, but for standard support workflows, it is acceptable.
     *
     * @return JsonResponse
     */
    public function fetch(): JsonResponse
    {
        if (!auth()->user()->isSupporter()) {
            return $this->errorResponse('Unauthorized access. Only supporters can sync emails.', 403);
        }

        try {
            // Invokes the same console command that runs in the background scheduler
            Artisan::call('app:fetch-support-emails');

            return $this->successResponse(
                null,
                'Emails fetched successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch emails: ' . $e->getMessage(), 500);
        }
    }
}