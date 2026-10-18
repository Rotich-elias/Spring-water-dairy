<?php

use App\Domain\Animals\Models\Animal;
use App\Models\Lookup;

test('owner can view dashboard stats', function () {
    $token = $this->authenticateAsOwner();

    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    Animal::factory()->count(3)->ofCategory($category->id)->create();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/dashboard/summary');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toHaveKey('total_animals');
    expect($data)->toHaveKey('animals_by_status');
    expect($data)->toHaveKey('animals_by_category');
    expect($data)->toHaveKey('lactating_cows');
    expect($data)->toHaveKey('todays_milk_litres');
    expect($data)->toHaveKey('heats_this_week');
    expect($data)->toHaveKey('services_this_week');
    expect($data)->toHaveKey('pregnancies_pending');
    expect($data)->toHaveKey('calvings_expected_this_week');
    expect($data)->toHaveKey('treatments_in_withholding');
    expect($data)->toHaveKey('vaccines_due');
    expect($data['total_animals'])->toBe(3);
});

test('unauthenticated user cannot view dashboard stats', function () {
    $this->getJson('/api/v1/dashboard/stats')
        ->assertStatus(401);
});
