<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $attendance = $this->route('attendance');
        $user = $this->user();

        if ($user?->isAdministrator()) {
            return true;
        }

        // Employees can only update their own attendance (e.g., checking out)
        return $attendance?->user_id === $user?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'out_at' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::enum(AttendanceStatus::class)],
            'proof_photo' => ['nullable', 'string'],
        ];

        if ($this->user()?->isAdministrator()) {
            $rules['user_id'] = ['sometimes', 'exists:users,id'];
            $rules['office_id'] = ['sometimes', 'exists:offices,id'];
            $rules['date'] = ['sometimes', 'date', 'date_format:Y-m-d'];
            $rules['in_at'] = ['sometimes', 'date'];
            $rules['in_latitude'] = ['sometimes', 'numeric', 'between:-90,90'];
            $rules['in_longitude'] = ['sometimes', 'numeric', 'between:-180,180'];
        }

        return $rules;
    }
}
