<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

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
     * Return a uniform success response with Laravel pagination metadata.
     *
     * @param  array<string, mixed>|mixed|null  $data
     */
    protected function paginatedSuccess(
        string $message,
        mixed $data,
        LengthAwarePaginator $paginator,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
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
