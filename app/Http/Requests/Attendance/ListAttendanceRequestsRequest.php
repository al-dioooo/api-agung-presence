<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceRequestStatus;
use App\Support\Pagination\PaginationOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAttendanceRequestsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approval_status' => ['sometimes', 'nullable', Rule::enum(AttendanceRequestStatus::class)],
            'covers_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
        ] + PaginationOptions::rules();
    }

    public function pagination(): PaginationOptions
    {
        return PaginationOptions::fromRequest($this);
    }
}
