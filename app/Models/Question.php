<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $table = 'questions';

    protected $primaryKey = "id_question";

    protected $visible = ['id_exam_type', 'id_theme', 'year', 'question', 'image', 'comment', 'image_comment', 'alt_a', 'alt_b', 'alt_c', 'alt_d', 'alt_e', 'response', 'justification', 'distractor_analysis', 'reference', 'image_justification', 'drbank', 'exam'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'status'
    ];

    public $timestamps = false;
}
