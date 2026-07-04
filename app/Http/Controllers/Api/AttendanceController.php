<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\FilterAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\StoreManualAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceReportDetailResource;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Office;
use App\Support\AttendanceReportBuilder;
use App\Support\AttendanceWorkdays;
use App\Support\Pagination\PaginatesCollections;
use App\Traits\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use ApiResponse;

    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Display a listing of the attendances.
     */
    public function index(FilterAttendanceRequest $request, AttendanceReportBuilder $reportBuilder): JsonResponse
    {
        $paginator = PaginatesCollections::paginate(
            $reportBuilder->detailRows($request->toFilter(), $request->user()),
            $request->pagination(),
            $request,
        );

        return $this->paginatedSuccess(
            'Attendances retrieved successfully.',
            AttendanceReportDetailResource::collection($paginator->getCollection()),
            $paginator,
        );
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

        $date = $data['date'] ?? $inAt->toDateString();
        $realAttendanceData = [
            ...$data,
            'attendance_request_id' => null,
            'date' => $date,
            'in_at' => $inAt,
            'status' => $this->resolveStatus($inAt, $office),
        ];

        $existingAbsent = Attendance::query()
            ->where('user_id', $data['user_id'])
            ->whereDate('date', $date)
            ->where('status', AttendanceStatus::Absent->value)
            ->first();

        if ($existingAbsent) {
            $existingAbsent->update([
                ...$realAttendanceData,
                'updated_by' => $request->user()?->username,
            ]);

            return $this->success(
                'Attendance updated successfully.',
                new AttendanceResource($existingAbsent->fresh(['user', 'office'])),
            );
        }

        $attendance = Attendance::create([
            ...$realAttendanceData,
            'created_by' => $request->user()?->username,
        ]);

        return $this->success('Attendance created successfully.', new AttendanceResource($attendance->load(['user', 'office'])), 201);
    }

    /**
     * Store or replace manual sick, leave, or permit attendance records.
     */
    public function storeManual(StoreManualAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $username = $request->user()?->username;

        if (isset($data['start_date'], $data['end_date'])) {
            $attendances = DB::transaction(function () use ($data, $username) {
                return collect(AttendanceWorkdays::dates($data['start_date'], $data['end_date']))
                    ->map(function (CarbonImmutable $date) use ($data, $username): Attendance {
                        [$attendance] = $this->storeManualAttendanceForDate(
                            (int) $data['user_id'],
                            $date->toDateString(),
                            AttendanceStatus::from($data['status']),
                            $username,
                        );

                        return $attendance;
                    });
            });

            return $this->success('Manual attendances stored successfully.', AttendanceResource::collection($attendances));
        }

        [$attendance, $created] = $this->storeManualAttendanceForDate(
            (int) $data['user_id'],
            $data['date'],
            AttendanceStatus::from($data['status']),
            $username,
        );

        return $this->success(
            $created ? 'Manual attendance created successfully.' : 'Manual attendance updated successfully.',
            new AttendanceResource($attendance),
            $created ? 201 : 200,
        );
    }

    /**
     * @return array{0: Attendance, 1: bool}
     */
    private function storeManualAttendanceForDate(
        int $userId,
        string $date,
        AttendanceStatus $status,
        ?string $username,
    ): array {
        $attendance = Attendance::query()
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->first();

        $manualData = [
            'office_id' => null,
            'attendance_request_id' => null,
            'date' => $date,
            'in_at' => null,
            'out_at' => null,
            'in_latitude' => null,
            'in_longitude' => null,
            'proof_photo' => null,
            'status' => $status,
            'updated_by' => $username,
        ];

        if ($attendance) {
            $attendance->update($manualData);

            return [$attendance->fresh(['user', 'office']), false];
        }

        $attendance = Attendance::create([
            ...$manualData,
            'user_id' => $userId,
            'created_by' => $username,
        ]);

        return [$attendance->load(['user', 'office']), true];
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
