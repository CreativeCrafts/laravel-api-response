<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Helpers\HateoasLinkGenerator;
use Illuminate\Support\Facades\Route;

covers(HateoasLinkGenerator::class);

beforeEach(function () {
    $this->linkGenerator = new HateoasLinkGenerator();
});

it('generates links correctly', function () {
    Route::get('/test', fn () => 'test')->name('test.route');
    Route::post('/create', fn () => 'create')->name('create.route');

    $links = [
        'self' => 'test.route',
        'create' => ['route' => 'create.route', 'params' => ['id' => 1]],
    ];

    $generatedLinks = $this->linkGenerator->generateLinks($links);

    expect($generatedLinks)->toHaveCount(2)
        ->and($generatedLinks['self'])->toHaveKeys(['href', 'rel', 'method'])
        ->and($generatedLinks['self']['method'])->toBe('GET')
        ->and($generatedLinks['create'])->toHaveKeys(['href', 'rel', 'method'])
        ->and($generatedLinks['create']['method'])->toBe('POST');
});

it('handles string route definitions', function () {
    Route::get('/test', fn () => 'test')->name('test.route');

    $links = ['self' => 'test.route'];

    $generatedLinks = $this->linkGenerator->generateLinks($links);

    expect($generatedLinks['self'])->toHaveKeys(['href', 'rel', 'method'])
        ->and($generatedLinks['self']['rel'])->toBe('self')
        ->and($generatedLinks['self']['method'])->toBe('GET');
});

it('handles array route definitions with parameters', function () {
    Route::get('/test/{id}', fn () => 'test')->name('test.route');

    $links = ['test' => ['route' => 'test.route', 'params' => ['id' => 1]]];

    $generatedLinks = $this->linkGenerator->generateLinks($links);

    expect($generatedLinks['test'])->toHaveKeys(['href', 'rel', 'method'])
        ->and($generatedLinks['test']['href'])->toContain('1')
        ->and($generatedLinks['test']['rel'])->toBe('test');
});

it('ignores invalid link definitions', function () {
    $links = [
        'valid' => 'test.route',
        'invalid' => 123,
        'another_invalid' => ['not_a_route' => 'something'],
    ];

    Route::get('/test', fn () => 'test')->name('test.route');

    $generatedLinks = $this->linkGenerator->generateLinks($links);

    expect($generatedLinks)->toHaveCount(1)
        ->and($generatedLinks)->toHaveKey('valid')
        ->and($generatedLinks)->not->toHaveKey('invalid')
        ->and($generatedLinks)->not->toHaveKey('another_invalid');
});

it('generates a single link correctly', function () {
    Route::put('/update/{id}', fn () => 'update')->name('update.route');

    $link = $this->linkGenerator->generate('update.route', ['id' => 5], 'update');

    expect($link)->toHaveKeys(['href', 'rel', 'method'])
        ->and($link['href'])->toContain('5')
        ->and($link['rel'])->toBe('update')
        ->and($link['method'])->toBe('PUT');
});

it('uses the first method for routes with multiple methods', function () {
    Route::match(['GET', 'POST'], '/multi', fn () => 'multi')->name('multi.route');

    $link = $this->linkGenerator->generate('multi.route');

    expect($link['method'])->toBe('GET');
});
