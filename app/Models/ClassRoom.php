<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    protected $table = 'classes';

    public $timestamps = false;

    protected $fillable = ['grade_id', 'name_ar'];

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function students()
    {
        return $this->hasMany(User::class, 'class_id');
    }

    public function servants()
    {
        return $this->belongsToMany(User::class, 'servant_classes', 'class_id', 'servant_id');
    }
}
