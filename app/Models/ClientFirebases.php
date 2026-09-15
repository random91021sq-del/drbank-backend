<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientFirebases extends Model
{
    protected $table = "client_firebases";

    protected $primaryKey = "id_client_firebase";

    protected $visible = ['id_client', 'token_firebase'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'status'
    ];

    public $timestamps = false;
}
