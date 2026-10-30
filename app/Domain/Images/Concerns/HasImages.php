<?php

namespace App\Domain\Images\Concerns;

use App\Domain\Images\Models\Image;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasImages
{
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')
            ->orderByDesc('is_primary')
            ->orderBy('id');
    }

    public function primaryImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')
            ->where('is_primary', true);
    }
}
