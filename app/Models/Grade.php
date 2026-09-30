<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $table = 'grades';

    public $timestamps = false;

    protected $fillable = ['stage_id', 'name_ar'];

    public function stage()
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function classes()
    {
        return $this->hasMany(ClassRoom::class, 'grade_id');
    }
}
