<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Year extends Model
{
    protected $table = "exam_year";
    protected $primaryKey = "id_exam_year";
    protected $visible = ["year"];
    protected $hidden = ["created_at"];
    public $timestamps = false;
}
