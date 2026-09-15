<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStudyPlan extends Model
{
    protected $table = 'student_study_plans';
    protected $primaryKey = 'id';
    protected $fillable = [
        'id_client',
        'plan_data',
        'generated_at',
    ];
}
