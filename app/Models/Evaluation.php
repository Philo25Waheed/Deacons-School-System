<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $table = 'evaluations';

    protected $fillable = ['student_id', 'servant_id', 'behavior_score', 'hymn_memorization', 'church_attending', 'notes', 'evaluation_date'];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function servant()
    {
        return $this->belongsTo(User::class, 'servant_id');
    }
}
