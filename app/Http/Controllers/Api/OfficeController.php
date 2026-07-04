<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Office\ListOfficesRequest;
use App\Http\Requests\Office\StoreOfficeRequest;
use App\Http\Requests\Office\UpdateOfficeRequest;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Support\Pagination\PaginatesCollections;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    use ApiResponse;

    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Display a listing of offices.
     */
    public function index(ListOfficesRequest $request): JsonResponse
    {
        $query = Office::query();

        if ($request->boolean('active_only') || ! $request->user()?->isAdministrator()) {
            $query->where('is_active', true);
        } elseif ($request->filled('active_status')) {
            $activeStatus = $request->validated('active_status');

            if ($activeStatus === 'active') {
                $query->where('is_active', true);
            } elseif ($activeStatus === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search')->trim();
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $pagination = $request->pagination();

        if ($request->validated('sort') === 'nearest') {
            $latitude = (float) $request->validated('latitude');
            $longitude = (float) $request->validated('longitude');
            $offices = $query
                ->get()
                ->map(function (Office $office) use ($latitude, $longitude): Office {
                    $office->setAttribute('distance_meters', $this->distanceInMeters(
                        $latitude,
                        $longitude,
                        (float) $office->latitude,
                        (float) $office->longitude,
                    ));

                    return $office;
                })
                ->sortBy('distance_meters')
                ->values();

            $paginator = PaginatesCollections::paginate($offices, $pagination, $request);
        } else {
            $query->orderBy('name');
            $paginator = $query->paginate($pagination->perPage, ['*'], 'page', $pagination->page);
        }

        return $this->paginatedSuccess(
            'Offices retrieved successfully.',
            OfficeResource::collection($paginator->getCollection()),
            $paginator,
        );
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

    private function distanceInMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $fromLatitudeRadians = deg2rad($fromLatitude);
        $toLatitudeRadians = deg2rad($toLatitude);
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $angle = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitudeRadians) * cos($toLatitudeRadians) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($angle), sqrt(1 - $angle));
    }
}
