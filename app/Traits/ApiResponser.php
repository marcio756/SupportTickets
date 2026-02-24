<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Trait to standardize API responses across the application.
 */
trait ApiResponser
{
    /**
     * Return a success JSON response.
     */
    protected function successResponse($data, string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'Success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Return an error JSON response.
     */
    protected function errorResponse(string $message = null, int $code): JsonResponse
    {
        return response()->json([
            'status' => 'Error',
            'message' => $message,
            'data' => null
        ], $code);
    }
}