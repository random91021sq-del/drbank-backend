<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDailyProgress extends Model
{
    protected $table = 'student_daily_progress';
    protected $primaryKey = 'id';
    protected $fillable = [
        'id_client',
        'theme_uuid',
        'study_date',
        'due_date',
        'weekly_progress_percentage',
        'status'
    ];
}
