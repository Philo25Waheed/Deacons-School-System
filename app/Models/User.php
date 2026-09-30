<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'password',
        'dob',
        'church_name',
        'deacon_rank',
        'gender',
        'address',
        'father_name',
        'father_phone',
        'mother_name',
        'mother_phone',
        'stage_id',
        'grade_id',
        'class_id',
        'profile_pic',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'dob' => 'date',
    ];

    public function stage()
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function points()
    {
        return $this->hasMany(Point::class, 'student_id');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'student_id');
    }

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class, 'student_id');
    }

    public function children()
    {
        return $this->belongsToMany(User::class, 'parent_student', 'parent_id', 'student_id');
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id');
    }

    public function servedClasses()
    {
        return $this->belongsToMany(ClassRoom::class, 'servant_classes', 'servant_id', 'class_id');
    }

    public function visitations()
    {
        return $this->hasMany(Visitation::class, 'student_id');
    }
}
