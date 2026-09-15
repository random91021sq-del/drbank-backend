<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicAdvisories extends Model
{
    protected $table = 'academic_advisories';

    protected $primaryKey = 'id';

    protected $fillable = [
        'client_id',
        'doctor_id',
        'scheduled_at',
        'duration_minutes',
        'reason',
        'status',
        'meeting_url',
        'google_calendar_event_id',
    ];

    protected $visible = ['scheduled_at', 'duration_minutes', 'reason', 'status', 'meeting_url', 'doctor_name','doctor_specialty'];

    protected $hidden = ['client_id', 'doctor_id'];

    public $timestamps = false;
}
