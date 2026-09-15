<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'report';

    protected $primaryKey = 'id_report';

    protected $visible = [
        'reason'
    ];

    protected $hidden = [
        'id_question',
        'id_client',
        'created_at'
    ];

    public $timestamps = false;
}
