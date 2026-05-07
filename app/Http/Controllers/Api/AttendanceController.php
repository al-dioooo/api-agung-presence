<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the attendances.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['user', 'office'])
            ->orderByDesc('date')
            ->orderByDesc('in_at');

        if (! $request->user()?->isAdministrator()) {
            $query->where('user_id', $request->user()?->id);
        }

        return $this->success('Attendances retrieved successfully.', AttendanceResource::collection($query->get()));
    }

    /**
     * Store a newly created attendance.
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $request->user()?->isAdministrator()) {
            $data['user_id'] = $request->user()?->id;
        }

        $attendance = Attendance::create([
            ...$data,
            'created_by' => $request->user()?->username,
        ]);

        return $this->success('Attendance created successfully.', new AttendanceResource($attendance->load(['user', 'office'])), 201);
    }

    /**
     * Display the specified attendance.
     */
    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        if (! $request->user()?->isAdministrator() && $request->user()?->id !== $attendance->user_id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success('Attendance retrieved successfully.', new AttendanceResource($attendance->load(['user', 'office'])));
    }

    /**
     * Update the specified attendance.
     */
    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        $attendance->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->username,
        ]);

        return $this->success('Attendance updated successfully.', new AttendanceResource($attendance->fresh(['user', 'office'])));
    }

    /**
     * Remove the specified attendance.
     */
    public function destroy(Request $request, Attendance $attendance): JsonResponse
    {
        if (! $request->user()?->isAdministrator()) {
            return $this->error('Unauthorized.', 403);
        }

        $attendance->delete();

        return $this->success('Attendance deleted successfully.');
    }
}
