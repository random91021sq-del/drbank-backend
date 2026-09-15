<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    protected $primaryKey = "id_category";

    protected $visible = ['id_group', 'title', 'description', 'code', 'icon'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'status'
    ];
    
    public $timestamps = false;
}
