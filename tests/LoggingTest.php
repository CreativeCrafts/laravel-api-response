<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Helpers\Logging;
use Illuminate\Support\Facades\Log;

covers(Logging::class);

beforeEach(function () {
    $this->logging = new Logging();
});

it('handles all HTTP status codes', function ($statusCode, $expectedLogMethod) {
    Log::shouldReceive($expectedLogMethod)->once()->with(
        'API Response - Method: GET, URL: https://api.example.com, Status: ' . $statusCode . ', Data: {"key":"value"}'
    );

    $this->logging->logResponse('GET', 'https://api.example.com', $statusCode, ['key' => 'value']);
})->with([
    [100, 'info'],
    [200, 'info'],
    [300, 'info'],
    [399, 'info'],
    [400, 'error'],
    [500, 'error']
]);
