<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiturgyRoster extends Model
{
    protected $table = 'liturgy_rosters';

    protected $fillable = ['liturgy_date', 'liturgy_name', 'altar_name', 'stage_id', 'student_ids', 'notes', 'created_by'];

    protected $casts = ['student_ids' => 'array', 'liturgy_date' => 'date'];
}
