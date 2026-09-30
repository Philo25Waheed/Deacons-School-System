<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Point extends Model
{
    protected $table = 'points';

    protected $fillable = ['student_id', 'servant_id', 'points', 'type', 'reason'];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function servant()
    {
        return $this->belongsTo(User::class, 'servant_id');
    }
}
