<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A public contact form submission, visible only to platform admins.
 */
class ContactInquiry extends Model
{
    protected $fillable = [
        'name',
        'farm_name',
        'email',
        'phone',
        'message',
        'status',
    ];

    protected $attributes = [
        'status' => 'new',
    ];
}
