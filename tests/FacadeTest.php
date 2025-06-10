<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Facades\LaravelApiResponse;

it('resolves the correct facade accessor', function () {
    $reflection = new ReflectionClass(LaravelApiResponse::class);
    $method = $reflection->getMethod('getFacadeAccessor');
    $method->setAccessible(true);

    expect($method->invoke(null))->toBe('laravel-api');
});

it('has proper docblock annotations', function () {
    $reflection = new ReflectionClass(LaravelApiResponse::class);
    $docComment = $reflection->getDocComment();

    expect($docComment)->toContain('@method');
    expect($docComment)->toContain('@see');
    expect($docComment)->toContain('@mixin');
});
