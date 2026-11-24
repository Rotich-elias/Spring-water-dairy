<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Global, non-tenant setting used by the public site and seller console.
 */
class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function getValueAttribute(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function setValueAttribute(mixed $value): void
    {
        if (is_bool($value)) {
            $this->attributes['type'] = 'boolean';
            $this->attributes['value'] = $value ? '1' : '0';

            return;
        }

        if (is_array($value) || is_object($value)) {
            $this->attributes['type'] = 'json';
            $this->attributes['value'] = json_encode($value);

            return;
        }

        $this->attributes['value'] = (string) $value;
    }
}
