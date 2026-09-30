<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hymn extends Model
{
    protected $table = 'hymns';

    protected $fillable = ['title', 'coptic_text', 'coptic_arabic_text', 'arabic_translation', 'description', 'audio_file', 'pdf_file', 'video_link', 'season', 'created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
