<?php

namespace App\Domain\Milk\Models;

use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    protected $fillable = [
        'model',
        'record_id',
        'action',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public $timestamps = false;
}
