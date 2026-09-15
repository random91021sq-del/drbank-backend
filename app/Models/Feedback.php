<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = "feedback";

    protected $primaryKey = "id_feedback";

    protected $visible = ['id_client', 'full_name', 'email', 'reason', 'description', 'response', 'to_emit'];

    protected $attributes = [
        'to_emit' => ''
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'status'
    ];

    public $timestamps = false;
}
