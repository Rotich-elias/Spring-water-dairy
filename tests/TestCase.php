<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Testing\Response;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Seed roles, lookups, settings and the owner user before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--force' => true]);
    }

    /**
     * Authenticate as the seeded Owner and return the bearer token.
     */
    protected function authenticateAsOwner(): string
    {
        $owner = User::where('email', 'owner@springwaterdairy.local')->first();

        $token = $owner->createToken('test')->plainTextToken;

        return $token;
    }

    /**
     * Make an authenticated JSON request.
     *
     * @return Response
     */
    protected function authJson(string $method, string $uri, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->authenticateAsOwner())
            ->json($method, '/api/v1'.$uri, $body);
    }
}
