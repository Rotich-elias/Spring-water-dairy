<?php

return [

    'currency' => env('BILLING_CURRENCY', 'KES'),

    /*
    |--------------------------------------------------------------------------
    | Default tenant
    |--------------------------------------------------------------------------
    |
    | The single farm created for existing installations and used as the
    | fallback for users that have not explicitly selected a farm.
    |
    */

    'default_tenant' => [
        'slug' => env('APP_TENANT_SLUG', 'spring-water-dairy'),
        'name' => env('APP_TENANT_NAME', env('APP_NAME', 'Spring Water Dairy')),
        'owner_email' => env('APP_OWNER_EMAIL', 'owner@springwaterdairy.local'),
    ],

];
