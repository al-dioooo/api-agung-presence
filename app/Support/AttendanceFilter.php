<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AttendanceFilter
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private readonly array $filters = []) {}

    /**
     * Apply filter constraints to an attendance query.
     *
     * @param  Builder<Attendance>  $query
     * @return Builder<Attendance>
     */
    public function apply(Builder $query, ?User $actor = null): Builder
    {
        $this->applyActorScope($query, $actor);
        $this->applyExplicitFilters($query, $actor);
        $this->applySearch($query);

        return $query;
    }

    public function hasConstraints(): bool
    {
        foreach ($this->filters as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->filters;
    }

    /**
     * @param  Builder<Attendance>  $query
     */
    private function applyActorScope(Builder $query, ?User $actor): void
    {
        if ($actor && ! $actor->isAdministrator()) {
            $query->where('user_id', $actor->id);
        }
    }

    /**
     * @param  Builder<Attendance>  $query
     */
    private function applyExplicitFilters(Builder $query, ?User $actor): void
    {
        if (($actor?->isAdministrator() ?? true) && $this->filled('user_id')) {
            $query->where('user_id', (int) $this->filters['user_id']);
        }

        if ($this->filled('office_id')) {
            $query->where('office_id', (int) $this->filters['office_id']);
        }

        if ($this->filled('status')) {
            $query->where('status', $this->filters['status']);
        }

        if ($this->filled('date')) {
            $query->whereDate('date', $this->filters['date']);
        }

        if ($this->filled('start_date')) {
            $query->whereDate('date', '>=', $this->filters['start_date']);
        }

        if ($this->filled('end_date')) {
            $query->whereDate('date', '<=', $this->filters['end_date']);
        }
    }

    /**
     * @param  Builder<Attendance>  $query
     */
    private function applySearch(Builder $query): void
    {
        if (! $this->filled('search')) {
            return;
        }

        $search = trim((string) $this->filters['search']);
        $like = '%'.$search.'%';
        $statuses = $this->matchingStatuses($search);

        $query->where(function (Builder $query) use ($like, $statuses): void {
            $query
                ->where('date', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhereHas('office', function (Builder $query) use ($like): void {
                    $query
                        ->where('name', 'like', $like)
                        ->orWhere('address', 'like', $like);
                })
                ->orWhereHas('user', function (Builder $query) use ($like): void {
                    $query
                        ->where('name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });

            if ($statuses !== []) {
                $query->orWhereIn('status', $statuses);
            }
        });
    }

    private function filled(string $key): bool
    {
        return isset($this->filters[$key]) && $this->filters[$key] !== '';
    }

    /**
     * @return array<int, string>
     */
    private function matchingStatuses(string $search): array
    {
        $needle = Str::lower($search);
        $labels = [
            AttendanceStatus::OnTime->value => ['on time', 'tepat waktu'],
            AttendanceStatus::Late->value => ['late', 'terlambat'],
            AttendanceStatus::Absent->value => ['absent', 'tidak hadir'],
            AttendanceStatus::Sick->value => ['sick', 'sakit'],
            AttendanceStatus::Leave->value => ['leave', 'cuti'],
            AttendanceStatus::Permit->value => ['permit', 'izin'],
        ];

        return collect($labels)
            ->filter(fn (array $values, string $status) => Str::contains($status, $needle)
                || collect($values)->contains(fn (string $label) => Str::contains($label, $needle)))
            ->keys()
            ->values()
            ->all();
    }
}
