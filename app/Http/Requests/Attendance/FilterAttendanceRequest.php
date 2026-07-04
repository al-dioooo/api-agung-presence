<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Support\AttendanceFilter;
use App\Support\Pagination\PaginationOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FilterAttendanceRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', Rule::enum(AttendanceStatus::class)],
            'office_id' => ['sometimes', 'integer', 'exists:offices,id'],
            'date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'start_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
        ] + PaginationOptions::rules();
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
                if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                    return;
                }

                if ($this->date('end_date')?->lt($this->date('start_date'))) {
                    $validator->errors()->add('end_date', 'The end date field must be a date after or equal to start date.');
                }
            },
        ];
    }

    public function toFilter(): AttendanceFilter
    {
        return new AttendanceFilter(Arr::except($this->validated(), ['page', 'per_page']));
    }

    public function pagination(): PaginationOptions
    {
        return PaginationOptions::fromRequest($this);
    }
}
