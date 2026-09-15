<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamType extends Model
{
    protected $table = "exam_type";
    protected $primaryKey = "id_exam_type";
    protected $visible = ["exam_type","exam"];
    protected $hidden = ["created_at"];
    public $timestamps = false;
}
