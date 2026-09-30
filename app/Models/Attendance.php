<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'servant_id',
        'attendance_date',
        'status',
        'attended_lesson',
        'attended_pamphlet',
        'attended_liturgy',
        'points_awarded',
        'scanned_at',
        'notes',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function servant()
    {
        return $this->belongsTo(User::class, 'servant_id');
    }
}
