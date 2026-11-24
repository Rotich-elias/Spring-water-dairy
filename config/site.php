<?php

$platformName = 'TetoDairy';
$parentCompany = 'Teto Technologies';
$parentCompanyShort = 'Teto';
$poweredByLabel = 'Powered by Teto Technologies';
$parentCompanyUrl = 'https://teto.co.ke';

return [

    /*
    |--------------------------------------------------------------------------
    | Platform branding
    |--------------------------------------------------------------------------
    |
    | Global identity for the TetoDairy platform. These values are mirrored
    | into site_settings by the seeder so a platform admin can edit them at
    | runtime without a deployment.
    |
    */

    'platform_name' => $platformName,
    'parent_company' => $parentCompany,
    'parent_company_short' => $parentCompanyShort,
    'powered_by_label' => $poweredByLabel,
    'parent_company_url' => $parentCompanyUrl,

    /*
    |--------------------------------------------------------------------------
    | Platform administrator bootstrap account
    |--------------------------------------------------------------------------
    |
    | Never commit real credentials; supply these through the environment.
    |
    */

    'platform_admin_email' => env('APP_PLATFORM_ADMIN_EMAIL', 'platform@tetodairy.local'),
    'platform_admin_password' => env('APP_PLATFORM_ADMIN_PASSWORD', 'change-me-immediately'),

    /*
    |--------------------------------------------------------------------------
    | Default site settings
    |--------------------------------------------------------------------------
    |
    | An ordered map of setting key to its definition. Public consumers fall
    | back to these values before the settings table has been seeded.
    |
    */

    'defaults' => [
        'platform_name' => ['value' => $platformName, 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'parent_company' => ['value' => $parentCompany, 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'parent_company_short' => ['value' => $parentCompanyShort, 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'powered_by_label' => ['value' => $poweredByLabel, 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'parent_company_url' => ['value' => $parentCompanyUrl, 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'business_name' => ['value' => 'TetoDairy', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'business_email' => ['value' => 'sales@tetodairy.local', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'business_phone' => ['value' => '+254 700 000 000', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'business_address' => ['value' => 'Nairobi, Kenya', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'business_logo_path' => ['value' => '/images/farm-logo.png', 'type' => 'string', 'group' => 'branding', 'is_public' => true],
        'invoice_payment_instructions' => ['value' => 'Pay the invoice total by bank transfer or mobile money. Quote the invoice number as the payment reference, then contact the TetoDairy team to confirm payment.', 'type' => 'string', 'group' => 'billing', 'is_public' => false],
        'hero_title' => ['value' => 'Modern dairy management for every farm', 'type' => 'string', 'group' => 'content', 'is_public' => true],
        'hero_subtitle' => ['value' => 'Keep every cow, litre and shilling accounted for in one place.', 'type' => 'string', 'group' => 'content', 'is_public' => true],
        'final_title' => ['value' => 'Ready to modernise your dairy?', 'type' => 'string', 'group' => 'content', 'is_public' => true],
        'final_subtitle' => ['value' => 'Join the farms already running with TetoDairy.', 'type' => 'string', 'group' => 'content', 'is_public' => true],
        'feature_items' => ['value' => [], 'type' => 'json', 'group' => 'content', 'is_public' => true],
        'step_items' => ['value' => [], 'type' => 'json', 'group' => 'content', 'is_public' => true],
        'faq_items' => ['value' => [], 'type' => 'json', 'group' => 'content', 'is_public' => true],
        'trust_items' => ['value' => [], 'type' => 'json', 'group' => 'content', 'is_public' => true],
    ],

];
