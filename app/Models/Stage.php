<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $table = 'stages';

    public $timestamps = false;

    protected $fillable = ['name_ar'];

    public function grades()
    {
        return $this->hasMany(Grade::class, 'stage_id');
    }

    public function students()
    {
        return $this->hasMany(User::class, 'stage_id');
    }
}
