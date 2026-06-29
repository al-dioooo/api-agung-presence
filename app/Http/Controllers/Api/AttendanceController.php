<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\StoreManualAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Office;
use App\Traits\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Display a listing of the attendances.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
        ]);

        $query = Attendance::with(['user', 'office', 'attendanceRequest.user', 'attendanceRequest.reviewer'])
            ->when($request->filled('office_id'), fn ($query) => $query->where('office_id', $request->integer('office_id')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('date', $request->date('date')))
            ->when($request->filled('start_date'), fn ($query) => $query->whereDate('date', '>=', $request->date('start_date')))
            ->when($request->filled('end_date'), fn ($query) => $query->whereDate('date', '<=', $request->date('end_date')))
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
        $office = Office::findOrFail($data['office_id']);
        $inAt = isset($data['in_at'])
            ? CarbonImmutable::parse($data['in_at'])
            : CarbonImmutable::now();

        if (! $request->user()?->isAdministrator()) {
            $data['user_id'] = $request->user()?->id;
        }

        if (! $office->is_active) {
            return $this->error('Office is inactive.', 422, [
                'errors' => [
                    'office_id' => ['Office is inactive.'],
                ],
            ]);
        }

        if ($this->distanceInMeters(
            (float) $data['in_latitude'],
            (float) $data['in_longitude'],
            (float) $office->latitude,
            (float) $office->longitude,
        ) > $office->radius) {
            return $this->error('Attendance location is outside the office radius.', 422, [
                'errors' => [
                    'in_latitude' => ['Attendance location is outside the office radius.'],
                ],
            ]);
        }

        $hasActiveAttendance = Attendance::query()
            ->where('user_id', $data['user_id'])
            ->whereNull('out_at')
            ->whereIn('status', [
                AttendanceStatus::OnTime->value,
                AttendanceStatus::Late->value,
            ])
            ->exists();

        if ($hasActiveAttendance) {
            return $this->error('User already has an active attendance. Please check out first.', 422, [
                'errors' => [
                    'user_id' => ['User already has an active attendance. Please check out first.'],
                ],
            ]);
        }

        $attendance = Attendance::create([
            ...$data,
            'date' => $data['date'] ?? $inAt->toDateString(),
            'in_at' => $inAt,
            'status' => $this->resolveStatus($inAt, $office),
            'created_by' => $request->user()?->username,
        ]);

        return $this->success('Attendance created successfully.', new AttendanceResource($attendance->load(['user', 'office'])), 201);
    }

    /**
     * Store or replace a manual sick or leave attendance record.
     */
    public function storeManual(StoreManualAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $attendance = Attendance::query()
            ->where('user_id', $data['user_id'])
            ->whereDate('date', $data['date'])
            ->first();

        $manualData = [
            'office_id' => null,
            'attendance_request_id' => null,
            'date' => $data['date'],
            'in_at' => null,
            'out_at' => null,
            'in_latitude' => null,
            'in_longitude' => null,
            'proof_photo' => null,
            'status' => AttendanceStatus::from($data['status']),
            'updated_by' => $request->user()?->username,
        ];

        if ($attendance) {
            $attendance->update($manualData);

            return $this->success('Manual attendance updated successfully.', new AttendanceResource($attendance->fresh(['user', 'office'])));
        }

        $attendance = Attendance::create([
            ...$manualData,
            'user_id' => $data['user_id'],
            'created_by' => $request->user()?->username,
        ]);

        return $this->success('Manual attendance created successfully.', new AttendanceResource($attendance->load(['user', 'office'])), 201);
    }

    /**
     * Display the specified attendance.
     */
    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        if (! $request->user()?->isAdministrator() && $request->user()?->id !== $attendance->user_id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success('Attendance retrieved successfully.', new AttendanceResource($attendance->load(['user', 'office', 'attendanceRequest.user', 'attendanceRequest.reviewer'])));
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

        return $this->success('Attendance updated successfully.', new AttendanceResource($attendance->fresh(['user', 'office', 'attendanceRequest.user', 'attendanceRequest.reviewer'])));
    }

    /**
     * Check out from an active attendance.
     */
    public function checkout(Request $request, Attendance $attendance): JsonResponse
    {
        if (! $request->user()?->isAdministrator() && $request->user()?->id !== $attendance->user_id) {
            return $this->error('Unauthorized.', 403);
        }

        if ($attendance->out_at !== null) {
            return $this->error('Attendance has already been checked out.', 422, [
                'errors' => [
                    'out_at' => ['Attendance has already been checked out.'],
                ],
            ]);
        }

        $attendance->update([
            'out_at' => now(),
            'updated_by' => $request->user()?->username,
        ]);

        return $this->success('Attendance checked out successfully.', new AttendanceResource($attendance->fresh(['user', 'office', 'attendanceRequest.user', 'attendanceRequest.reviewer'])));
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

    private function resolveStatus(CarbonImmutable $inAt, Office $office): AttendanceStatus
    {
        $workStart = CarbonImmutable::parse($inAt->toDateString().' '.$office->work_start_time);

        return $inAt->gt($workStart)
            ? AttendanceStatus::Late
            : AttendanceStatus::OnTime;
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
