<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialProfile extends Model
{
    protected $table = "social_profiles";

    protected $primaryKey = "id_social_profile";

    protected $visible = ['id_client', 'social_id', 'social_name', 'social_avatar'];

    protected $fillable = [
        'id_client',
        'social_id',
        'social_name',
        'social_avatar',
        'status'
    ];

    protected $hidden = [
        'status',
        'created_at',
        'updated_at',
    ];

    public $timestamps = false;
}
