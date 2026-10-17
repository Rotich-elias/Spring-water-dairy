<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Seed roles, lookups, settings and the owner user before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--force' => true]);
    }

    /**
     * Authenticate as the seeded Owner and return the bearer token.
     *
     * @return string
     */
    protected function authenticateAsOwner(): string
    {
        $owner = \App\Models\User::where('email', 'owner@springwaterdairy.local')->first();

        $token = $owner->createToken('test')->plainTextToken;

        return $token;
    }

    /**
     * Make an authenticated JSON request.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $body
     * @return \Illuminate\Http\Testing\Response
     */
    protected function authJson(string $method, string $uri, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->authenticateAsOwner())
            ->json($method, '/api/v1'.$uri, $body);
    }
}
