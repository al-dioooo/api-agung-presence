<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Any authenticated user can create an attendance (check-in)
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'office_id' => ['required', 'exists:offices,id'],
            'date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'in_at' => ['sometimes', 'date'],
            'in_latitude' => ['required', 'numeric', 'between:-90,90'],
            'in_longitude' => ['required', 'numeric', 'between:-180,180'],
            'proof_photo' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(AttendanceStatus::class)],
        ];

        if ($this->user()?->isAdministrator()) {
            $rules['user_id'] = ['required', 'exists:users,id'];
        }

        return $rules;
    }
}
