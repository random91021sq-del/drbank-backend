<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSlotHold extends Model
{
    protected $table = 'appointment_slot_holds';

    protected $fillable = [
        'doctor_id',
        'client_id',
        'scheduled_at',
        'expires_at',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
