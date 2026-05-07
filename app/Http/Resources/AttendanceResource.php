<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'office_id' => $this->office_id,
            'date' => $this->date?->format('Y-m-d'),
            'in_at' => $this->in_at?->toIso8601String(),
            'out_at' => $this->out_at?->toIso8601String(),
            'in_latitude' => $this->in_latitude,
            'in_longitude' => $this->in_longitude,
            'proof_photo' => $this->proof_photo,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'office' => new OfficeResource($this->whenLoaded('office')),
        ];
    }
}
