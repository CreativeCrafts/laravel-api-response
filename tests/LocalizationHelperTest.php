<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Helpers\LocalizationHelper;
use Illuminate\Support\Facades\Lang;

covers(LocalizationHelper::class);

it('returns translated message when translation exists', function () {
    Lang::shouldReceive('has')->with('test.message')->andReturn(true);
    Lang::shouldReceive('get')->with('test.message', [], null)->andReturn('Translated Message');

    $helper = new LocalizationHelper();
    $result = $helper->localize('test.message');

    expect($result)->toBe('Translated Message');
});

it('returns original message when translation does not exist', function () {
    Lang::shouldReceive('has')->with('test.message')->andReturn(false);

    $helper = new LocalizationHelper();
    $result = $helper->localize('test.message');

    expect($result)->toBe('test.message');
});

it('handles empty string input', function () {
    $helper = new LocalizationHelper();
    $result = $helper->localize('');

    expect($result)->toBe('');
});
