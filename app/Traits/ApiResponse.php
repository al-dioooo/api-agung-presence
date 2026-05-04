<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a uniform success response.
     *
     * @param  array<string, mixed>|mixed|null  $data
     */
    protected function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a uniform error response.
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function error(string $message, int $status = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
