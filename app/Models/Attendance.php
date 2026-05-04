<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected function casts(): array
    {
        return [
            'in_at' => 'datetime',
            'out_at' => 'datetime',
            'date' => 'date',
            'in_latitude' => 'decimal:8',
            'in_longitude' => 'decimal:8',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }
}
