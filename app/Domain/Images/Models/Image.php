<?php

namespace App\Domain\Images\Models;

use App\Models\User;
use Database\Factories\ImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'imageable_type',
        'imageable_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'is_primary',
        'uploaded_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'size' => 'integer',
    ];

    public static function newFactory(): ImageFactory
    {
        return ImageFactory::new();
    }

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk(config('images.disk', 'public'))->url($this->path);
    }

    public function getImageableAliasAttribute(): string
    {
        foreach (config('images.types', []) as $alias => $type) {
            if ($this->imageable_type === $type['model']) {
                return $alias;
            }
        }

        return $this->imageable_type;
    }
}
