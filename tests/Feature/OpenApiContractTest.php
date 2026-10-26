<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

test('every api route is documented in the openapi contract', function () {
    $specPath = base_path('../docs/openapi.yaml');

    if (! file_exists($specPath)) {
        $this->markTestSkipped('The shared OpenAPI contract is not available in this checkout.');
    }

    $spec = Yaml::parseFile($specPath);

    $documented = [];
    foreach ($spec['paths'] as $path => $operations) {
        $normalizedPath = preg_replace('/\{[^}]+\}/', '{}', $path);
        foreach (array_keys($operations) as $method) {
            $documented[] = strtoupper($method).' '.$normalizedPath;
        }
    }

    $actual = [];
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/v1/')) {
            continue;
        }

        $normalizedPath = preg_replace(
            '/\{[^}]+\}/',
            '{}',
            '/'.substr($uri, strlen('api/v1/'))
        );

        foreach ($route->methods() as $method) {
            if (in_array($method, ['GET', 'POST', 'PATCH', 'DELETE', 'PUT'], true)) {
                $actual[] = $method.' '.$normalizedPath;
            }
        }
    }

    $actual = array_values(array_unique($actual));
    $documented = array_values(array_unique($documented));
    sort($actual);
    sort($documented);

    expect($actual)->toBe($documented);
});

test('openapi operation ids are unique', function () {
    $specPath = base_path('../docs/openapi.yaml');

    if (! file_exists($specPath)) {
        $this->markTestSkipped('The shared OpenAPI contract is not available in this checkout.');
    }

    $spec = Yaml::parseFile($specPath);

    $operationIds = [];
    foreach ($spec['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            if (isset($operation['operationId'])) {
                $operationIds[] = $operation['operationId'];
            }
        }
    }

    expect($operationIds)->toHaveCount(count(array_unique($operationIds)));
});
