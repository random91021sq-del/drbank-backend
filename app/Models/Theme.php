<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    protected $table = 'themes';

    protected $primaryKey = 'id_theme';

    protected $visible = [
        'id',
        'themeId',
        'theme',
        'code',
        'description',
        'icon'
    ];

    protected $hidden = [
        'id_specialty',
        'status',
        'created_at',
        'updated_at'
    ];

    public $timestamps = false;
}
