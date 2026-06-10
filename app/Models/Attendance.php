<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'office_id',
        'date',
        'in_at',
        'out_at',
        'in_latitude',
        'in_longitude',
        'proof_photo',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'in_at' => 'datetime',
            'out_at' => 'datetime',
            'date' => 'date',
            'in_latitude' => 'decimal:8',
            'in_longitude' => 'decimal:8',
            'status' => AttendanceStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
