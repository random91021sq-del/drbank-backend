<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ExamType extends Model
{
    protected $table = "exam_type";
    protected $primaryKey = "id_exam_type";
    protected $visible = ["exam_type","exam"];
    protected $hidden = ["created_at"];
    public $timestamps = false;
    
    protected function exam():Attribute
    {
        return Attribute::make(
            get: fn ($value) => mb_strtoupper($value, 'UTF-8'),
        );
    }
}
