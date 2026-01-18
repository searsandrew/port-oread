<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'email',
        'tiber_user_id',
        'connected_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
    ];
}
