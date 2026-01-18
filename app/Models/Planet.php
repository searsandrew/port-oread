<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Planet extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'flavor',
        'type',
        'class',
        'victory_point_value',
        'filename',
        'is_standard',
        'is_purchasable',
        'is_custom',
        'is_promotional',
    ];

    protected $casts = [
        'victory_point_value' => 'int',
        'is_standard' => 'bool',
        'is_purchasable' => 'bool',
        'is_custom' => 'bool',
        'is_promotional' => 'bool',
    ];
}
