<?php

use App\Domain\Animals\Models\Animal;

return [

    /*
    |--------------------------------------------------------------------------
    | Image storage
    |--------------------------------------------------------------------------
    |
    | Images are written to the configured disk and served from its public URL.
    | The size limit is expressed in kilobytes, matching Laravel's max rule.
    |
    */

    'disk' => env('IMAGE_DISK', 'public'),

    'max_size' => (int) env('IMAGE_MAX_SIZE', 5120),

    /*
    |--------------------------------------------------------------------------
    | Imageable types
    |--------------------------------------------------------------------------
    |
    | Maps the API-facing type alias to the model it belongs to and the
    | permission prefix used for authorization. Add an entry here and use the
    | HasImages trait on the model to make another record type imageable.
    |
    */

    'types' => [
        'animal' => [
            'model' => Animal::class,
            'permission' => 'animals',
        ],
    ],

];
