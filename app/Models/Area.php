<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'areas';
    protected $primaryKey = 'id_area';
    protected $visable = ['id_area', 'area'];
    protected $hidden = ['status', 'created_at', 'update_at'];
    public $timestamps = false;
}
