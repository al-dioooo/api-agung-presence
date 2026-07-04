<?php

namespace App\Http\Requests\Office;

use App\Support\Pagination\PaginationOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListOfficesRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('active_only')) {
            $activeOnly = filter_var(
                $this->input('active_only'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );

            if ($activeOnly !== null) {
                $this->merge(['active_only' => $activeOnly]);
            }
        }
    }

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
            'active_only' => ['sometimes', 'boolean'],
            'active_status' => ['sometimes', 'nullable', Rule::in(['all', 'active', 'inactive'])],
            'sort' => ['sometimes', 'nullable', Rule::in(['name', 'nearest'])],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
        ] + PaginationOptions::rules(allowLimitAlias: true);
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
                if ((string) $this->string('sort') !== 'nearest') {
                    return;
                }

                if (! $this->filled('latitude')) {
                    $validator->errors()->add('latitude', 'The latitude field is required when sort is nearest.');
                }

                if (! $this->filled('longitude')) {
                    $validator->errors()->add('longitude', 'The longitude field is required when sort is nearest.');
                }
            },
        ];
    }

    public function pagination(): PaginationOptions
    {
        return PaginationOptions::fromRequest($this, allowLimitAlias: true);
    }
}
