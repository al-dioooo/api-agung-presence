<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceRequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approval_status' => [
                'required',
                Rule::in([
                    AttendanceRequestStatus::Approved->value,
                    AttendanceRequestStatus::Rejected->value,
                ]),
            ],
            'rejection_reason' => [
                'required_if:approval_status,'.AttendanceRequestStatus::Rejected->value,
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
