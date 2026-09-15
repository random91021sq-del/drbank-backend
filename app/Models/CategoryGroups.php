<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryGroups extends Model
{
    protected $primaryKey = "id_group";

    protected $visible = ['title', 'color'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'status'
    ];

    public $timestamps = false;
}
