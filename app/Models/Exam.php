<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $table = 'exams';

    protected $fillable = ['title', 'stage_id', 'grade_id', 'duration_minutes', 'reward_points', 'pass_percentage', 'total_score', 'is_active', 'created_by'];

    public function questions()
    {
        return $this->hasMany(Question::class, 'exam_id');
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class, 'exam_id');
    }
}
