<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Support\AttendanceWorkdays;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualAttendanceRequest extends FormRequest
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
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required_without:start_date', 'date', 'date_format:Y-m-d'],
            'start_date' => ['required_without:date', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required_with:start_date', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => [
                'required',
                Rule::in([
                    AttendanceStatus::Sick->value,
                    AttendanceStatus::Leave->value,
                    AttendanceStatus::Permit->value,
                ]),
            ],
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

                if ($this->filled('date') && ($this->filled('start_date') || $this->filled('end_date'))) {
                    $validator->errors()->add('date', 'The date field cannot be combined with start date or end date.');

                    return;
                }

                if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                    return;
                }

                if (AttendanceWorkdays::count($this->input('start_date'), $this->input('end_date')) === 0) {
                    $validator->errors()->add('start_date', 'The selected date range must include at least one workday.');
                }
            },
        ];
    }
}
