<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Support\AttendanceAbsencePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isEmployee() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in([
                    AttendanceStatus::Sick->value,
                    AttendanceStatus::Leave->value,
                    AttendanceStatus::Permit->value,
                ]),
            ],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'description' => ['required', 'string', 'max:2000'],
            'proof_photo' => ['required', 'string'],
        ];
    }

    /**
     * Get the after validation callbacks for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $userId = $this->user()?->id;
                $startDate = $this->date('start_date')?->toDateString();
                $endDate = $this->date('end_date')?->toDateString();

                if (! $userId || ! $startDate || ! $endDate) {
                    return;
                }

                if (
                    $this->input('type') === AttendanceStatus::Sick->value
                    && CarbonImmutable::parse($startDate, AttendanceAbsencePolicy::TIMEZONE)
                        ->startOfDay()
                        ->gt(CarbonImmutable::parse(now(AttendanceAbsencePolicy::TIMEZONE))->startOfDay())
                ) {
                    $validator->errors()->add('start_date', 'Tanggal mulai sakit tidak boleh lebih dari hari ini.');
                }

                $hasOverlappingRequest = AttendanceRequest::query()
                    ->where('user_id', $userId)
                    ->whereIn('approval_status', [
                        AttendanceRequestStatus::Pending->value,
                        AttendanceRequestStatus::Approved->value,
                    ])
                    ->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate)
                    ->exists();

                if ($hasOverlappingRequest) {
                    $validator->errors()->add('start_date', 'Request overlaps with an existing pending or approved request.');
                }

                $hasRealAttendance = Attendance::query()
                    ->where('user_id', $userId)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->whereIn('status', [
                        AttendanceStatus::OnTime->value,
                        AttendanceStatus::Late->value,
                    ])
                    ->exists();

                if ($hasRealAttendance) {
                    $validator->errors()->add('start_date', 'Request range contains an existing real check-in attendance.');
                }
            },
        ];
    }
}
