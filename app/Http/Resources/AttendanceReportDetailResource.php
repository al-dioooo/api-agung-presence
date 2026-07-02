<?php

namespace App\Http\Resources;

use App\Support\ProjectedAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectedAttendance
 */
class AttendanceReportDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProjectedAttendance $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'virtual_key' => $row->virtualKey,
            'is_virtual' => $row->isVirtual,
            'user_id' => $row->userId,
            'attendance_request_id' => $row->attendanceRequestId,
            'office_id' => $row->officeId,
            'date' => $row->date->format('Y-m-d'),
            'in_at' => $row->inAt?->toIso8601String(),
            'out_at' => $row->outAt?->toIso8601String(),
            'in_latitude' => $row->inLatitude,
            'in_longitude' => $row->inLongitude,
            'proof_photo' => $row->proofPhoto,
            'status' => $row->status->value,
            'created_by' => $row->createdBy,
            'updated_by' => $row->updatedBy,
            'created_at' => $row->createdAt,
            'updated_at' => $row->updatedAt,
            'user' => $row->user ? new UserResource($row->user) : null,
            'office' => $row->office ? new OfficeResource($row->office) : null,
            'attendance_request' => $row->attendanceRequest ? new AttendanceRequestResource($row->attendanceRequest) : null,
        ];
    }
}
