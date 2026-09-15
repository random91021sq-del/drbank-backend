<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    protected $table = 'specialties';

    protected $primaryKey = 'id_specialty';

    protected $visible = ['id_area', 'specialty', 'code', '	description', 'icon'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'status'
    ];

    public $timestamps = false;
}
