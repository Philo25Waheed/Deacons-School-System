<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    protected $table = 'rewards';

    protected $fillable = ['title', 'description', 'points_required', 'quantity', 'image_url', 'is_available'];
}
