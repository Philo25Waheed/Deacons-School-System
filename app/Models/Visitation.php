<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitation extends Model
{
    protected $table = 'visitations';

    protected $fillable = ['student_id', 'servant_id', 'visitation_type', 'visit_date', 'notes', 'next_visit_date'];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function servant()
    {
        return $this->belongsTo(User::class, 'servant_id');
    }
}
