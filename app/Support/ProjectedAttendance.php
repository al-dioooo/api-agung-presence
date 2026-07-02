<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ProjectedAttendance
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly ?int $attendanceRequestId,
        public readonly ?int $officeId,
        public readonly CarbonInterface $date,
        public readonly ?CarbonInterface $inAt,
        public readonly ?CarbonInterface $outAt,
        public readonly mixed $inLatitude,
        public readonly mixed $inLongitude,
        public readonly ?string $proofPhoto,
        public readonly AttendanceStatus $status,
        public readonly ?string $createdBy,
        public readonly ?string $updatedBy,
        public readonly mixed $createdAt,
        public readonly mixed $updatedAt,
        public readonly ?User $user,
        public readonly ?Office $office,
        public readonly ?AttendanceRequest $attendanceRequest,
        public readonly bool $isVirtual = false,
        public readonly ?string $virtualKey = null,
    ) {}

    public static function fromAttendance(Attendance $attendance): self
    {
        return new self(
            id: $attendance->id,
            userId: $attendance->user_id,
            attendanceRequestId: $attendance->attendance_request_id,
            officeId: $attendance->office_id,
            date: CarbonImmutable::parse($attendance->date),
            inAt: $attendance->in_at,
            outAt: $attendance->out_at,
            inLatitude: $attendance->in_latitude,
            inLongitude: $attendance->in_longitude,
            proofPhoto: $attendance->proof_photo,
            status: $attendance->status,
            createdBy: $attendance->created_by,
            updatedBy: $attendance->updated_by,
            createdAt: $attendance->created_at,
            updatedAt: $attendance->updated_at,
            user: $attendance->relationLoaded('user') ? $attendance->user : null,
            office: $attendance->relationLoaded('office') ? $attendance->office : null,
            attendanceRequest: $attendance->relationLoaded('attendanceRequest')
                ? $attendance->attendanceRequest
                : null,
        );
    }

    public static function virtual(User $user, CarbonInterface $date, AttendanceStatus $status, ?AttendanceRequest $attendanceRequest = null): self
    {
        return new self(
            id: null,
            userId: $user->id,
            attendanceRequestId: $attendanceRequest?->id,
            officeId: null,
            date: CarbonImmutable::parse($date)->startOfDay(),
            inAt: null,
            outAt: null,
            inLatitude: null,
            inLongitude: null,
            proofPhoto: $attendanceRequest?->proof_photo,
            status: $status,
            createdBy: 'system',
            updatedBy: null,
            createdAt: null,
            updatedAt: null,
            user: $user,
            office: null,
            attendanceRequest: $attendanceRequest,
            isVirtual: true,
            virtualKey: 'virtual-'.$user->id.'-'.$date->format('Y-m-d'),
        );
    }
}
