<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    protected $table = 'exam_attempts';

    protected $fillable = ['exam_id', 'student_id', 'answers', 'score', 'status', 'servant_notes'];

    protected $casts = ['answers' => 'array'];

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
