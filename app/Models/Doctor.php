<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $table = 'doctors';

    protected $primaryKey = 'id';

    protected $visible = ['id', 'doctor_name', 'specialty'];

    public $timestamp = false;
}
