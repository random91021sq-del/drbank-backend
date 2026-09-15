<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    protected $table = "history";

    protected $primaryKey = "id_history";

    protected $fillable = ["id_client", "id_theme", "ok", "error", "empty", "count"];

    protected $hidden = ["created_at"];

    public $timestamps = false;

    protected function ok():Attribute
    {
        return Attribute::make(
            get: fn($value) => (int) $value,
            set: fn($value) => $value
        );
    }
    protected function error():Attribute
    {
        return Attribute::make(
            get: fn($value) => (int) $value,
            set: fn($value) => $value
        );
    }
    protected function empty():Attribute
    {
        return Attribute::make(
            get: fn($value) => (int) $value,
            set: fn($value) => $value
        );
    }
    protected function count():Attribute
    {
        return Attribute::make(
            get: fn($value) => (int) $value,
            set: fn($value) => $value
        );
    }
}