<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Client extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'clients';

    protected $primaryKey = 'id_client';

    protected $visible = ['profileId', 'name', 'last_name', 'email', 'phone', 'points','code_active','university', 'status'];
    protected $fillable=['name','last_name','email','phone','points','university','code_active','status'];
    protected $attributes = [
        'phone' => '',
        'photo' => '',
        'last_name' => '',
        'code_active' => '',
        'token' => '',
        'remember_token' => '',
        'latitud' => '',
        'longitud' => '',
        'equipment_uuid' => '',
        'token_firebase' => '',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucwords($value),
            set: fn ($value) => strtolower($value),
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucwords($value),
            set: fn ($value) => strtolower($value),
        );
    }

    protected function points(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => (int) $value,
            set: fn ($value) => $value,
        );
    }

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public $timestamps = false;
}
