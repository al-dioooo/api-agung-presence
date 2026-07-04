<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ListAttendanceRequestsRequest;
use App\Http\Requests\Attendance\ReviewAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceApplicationRequest;
use App\Http\Resources\AttendanceRequestResource;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Support\AttendanceAbsencePolicy;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceRequestController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of attendance requests.
     */
    public function index(ListAttendanceRequestsRequest $request): JsonResponse
    {
        $query = AttendanceRequest::with(['user', 'reviewer'])
            ->when(
                $request->filled('approval_status'),
                fn ($query) => $query->where('approval_status', $request->validated('approval_status')),
            )
            ->when(
                $request->filled('covers_date'),
                fn ($query) => $query
                    ->whereDate('start_date', '<=', $request->validated('covers_date'))
                    ->whereDate('end_date', '>=', $request->validated('covers_date')),
            )
            ->orderByRaw("case when approval_status = 'pending' then 0 else 1 end")
            ->orderByDesc('created_at');

        if (! $request->user()?->isAdministrator()) {
            $query->where('user_id', $request->user()?->id);
        }

        $pagination = $request->pagination();
        $paginator = $query->paginate($pagination->perPage, ['*'], 'page', $pagination->page);

        return $this->paginatedSuccess(
            'Attendance requests retrieved successfully.',
            AttendanceRequestResource::collection($paginator->getCollection()),
            $paginator,
        );
    }

    /**
     * Store a newly created attendance request.
     */
    public function store(StoreAttendanceApplicationRequest $request): JsonResponse
    {
        $attendanceRequest = AttendanceRequest::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'approval_status' => AttendanceRequestStatus::Pending,
            'created_by' => $request->user()->username,
        ]);

        return $this->success(
            'Attendance request created successfully.',
            new AttendanceRequestResource($attendanceRequest->load(['user', 'reviewer'])),
            201,
        );
    }

    /**
     * Display the specified attendance request.
     */
    public function show(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        if (! $request->user()?->isAdministrator() && $request->user()?->id !== $attendanceRequest->user_id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success(
            'Attendance request retrieved successfully.',
            new AttendanceRequestResource($attendanceRequest->load(['user', 'reviewer'])),
        );
    }

    /**
     * Approve or reject the specified attendance request.
     */
    public function review(
        ReviewAttendanceRequest $request,
        AttendanceRequest $attendanceRequest,
        AttendanceAbsencePolicy $absencePolicy,
    ): JsonResponse {
        if ($attendanceRequest->approval_status !== AttendanceRequestStatus::Pending) {
            return $this->error('Attendance request has already been reviewed.', 422, [
                'errors' => [
                    'approval_status' => ['Attendance request has already been reviewed.'],
                ],
            ]);
        }

        $data = $request->validated();
        $approvalStatus = AttendanceRequestStatus::from($data['approval_status']);

        if ($approvalStatus === AttendanceRequestStatus::Approved && $this->hasRealAttendanceConflict($attendanceRequest)) {
            return $this->error('Request range contains an existing real check-in attendance.', 422, [
                'errors' => [
                    'start_date' => ['Request range contains an existing real check-in attendance.'],
                ],
            ]);
        }

        DB::transaction(function () use ($attendanceRequest, $approvalStatus, $data, $request, $absencePolicy): void {
            if ($approvalStatus === AttendanceRequestStatus::Approved) {
                $this->materializeApprovedRequest($attendanceRequest, $request->user()->username, $absencePolicy);
            }

            $attendanceRequest->update([
                'approval_status' => $approvalStatus,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $approvalStatus === AttendanceRequestStatus::Rejected
                    ? $data['rejection_reason']
                    : null,
                'updated_by' => $request->user()->username,
            ]);
        });

        $message = $approvalStatus === AttendanceRequestStatus::Approved
            ? 'Attendance request approved successfully.'
            : 'Attendance request rejected successfully.';

        return $this->success(
            $message,
            new AttendanceRequestResource($attendanceRequest->fresh(['user', 'reviewer'])),
        );
    }

    private function hasRealAttendanceConflict(AttendanceRequest $attendanceRequest): bool
    {
        return Attendance::query()
            ->where('user_id', $attendanceRequest->user_id)
            ->whereBetween('date', [
                $attendanceRequest->start_date->toDateString(),
                $attendanceRequest->end_date->toDateString(),
            ])
            ->whereIn('status', [
                AttendanceStatus::OnTime->value,
                AttendanceStatus::Late->value,
            ])
            ->exists();
    }

    private function materializeApprovedRequest(
        AttendanceRequest $attendanceRequest,
        string $reviewerUsername,
        AttendanceAbsencePolicy $absencePolicy,
    ): void {
        foreach ($absencePolicy->workdayDates($attendanceRequest->start_date, $attendanceRequest->end_date) as $cursor) {
            $attendance = Attendance::query()
                ->where('user_id', $attendanceRequest->user_id)
                ->whereDate('date', $cursor->toDateString())
                ->whereIn('status', $absencePolicy->nonRealStatusValues())
                ->first();

            $manualData = [
                'attendance_request_id' => $attendanceRequest->id,
                'office_id' => null,
                'date' => $cursor->toDateString(),
                'in_at' => null,
                'out_at' => null,
                'in_latitude' => null,
                'in_longitude' => null,
                'proof_photo' => $attendanceRequest->proof_photo,
                'status' => $attendanceRequest->type,
                'updated_by' => $reviewerUsername,
            ];

            if ($attendance) {
                $attendance->update($manualData);
            } else {
                Attendance::create([
                    ...$manualData,
                    'user_id' => $attendanceRequest->user_id,
                    'created_by' => $reviewerUsername,
                ]);
            }
        }
    }
}
