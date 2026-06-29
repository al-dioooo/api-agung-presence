<?php

namespace App\Models;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRequest extends Model
{
    /** @use HasFactory<AttendanceRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'description',
        'proof_photo',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'approval_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttendanceStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'approval_status' => AttendanceRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
