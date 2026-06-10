<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\StoreOfficeRequest;
use App\Http\Requests\Office\UpdateOfficeRequest;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of offices.
     */
    public function index(Request $request): JsonResponse
    {
        $offices = Office::query()
            ->when(
                $request->boolean('active_only') || ! $request->user()?->isAdministrator(),
                fn ($query) => $query->where('is_active', true)
            )
            ->orderBy('name')
            ->get();

        return $this->success('Offices retrieved successfully.', OfficeResource::collection($offices));
    }

    /**
     * Store a newly created office.
     */
    public function store(StoreOfficeRequest $request): JsonResponse
    {
        $office = Office::create([
            ...$request->validated(),
            'created_by' => $request->user()->username,
        ]);

        return $this->success('Office created successfully.', new OfficeResource($office), 201);
    }

    /**
     * Display the specified office.
     */
    public function show(Request $request, Office $office): JsonResponse
    {
        if (! $request->user()?->isAdministrator() && ! $office->is_active) {
            return $this->error('Office not found.', 404);
        }

        return $this->success('Office retrieved successfully.', new OfficeResource($office));
    }

    /**
     * Update the specified office.
     */
    public function update(UpdateOfficeRequest $request, Office $office): JsonResponse
    {
        $office->update([
            ...$request->validated(),
            'updated_by' => $request->user()->username,
        ]);

        return $this->success('Office updated successfully.', new OfficeResource($office->fresh()));
    }

    /**
     * Remove the specified office (soft delete).
     */
    public function destroy(Request $request, Office $office): JsonResponse
    {
        if (! $request->user()?->isAdministrator()) {
            return $this->error('Unauthorized.', 403);
        }

        $office->delete();

        return $this->success('Office deleted successfully.');
    }
}
