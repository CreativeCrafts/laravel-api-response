<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\LaravelApi;

it('can respond with success', function () {
    $message = 'Success message';

    $response = LaravelApi::successResponse($message);
    expect($response)->toBeObject()
        ->and($response->getData())->toBeObject()
        ->and($response->getData()->success)->toBeTrue()
        ->and($response->getData()->message)->toEqual($message);
});

it('can respond with success and data', function () {
    $data = [
        'id' => 1,
        'name' => 'Test',
    ];

    $message = 'Success message';

    $response = LaravelApi::successResponse($message, $data);
    expect($response)->toBeObject()
        ->and($response->getData())->toBeObject()
        ->and($response->getData()->success)->toBeTrue()
        ->and($response->getData()->message)->toEqual($message)
        ->and($response->getData()->data->id)->toBe(1)
        ->and($response->getData()->data->name)->toEqual('Test');
});

it('can respond with exception', function () {
    $exception = new Exception('Test exception');
    $message = 'Internal server error';
    $errorCodes = 5001;
    $statusCode = 500;

    $response = LaravelApi::errorResponse($message, $statusCode, $exception, $errorCodes);
    expect($response)->toBeObject()
        ->and($response->getData())->toBeObject()
        ->and($response->getData()->success)->toBeFalse()
        ->and($response->getData()->exception)->toBeObject()
        ->and($response->getData()->exception->message)->toEqual($exception->getMessage());

    if (config('app.env') !== 'production') {
        expect($response->getData())->toHaveKey('exception')
            ->and($response->getData()->exception)->toBeObject()
            ->and($response->getData()->exception->message)->toEqual($exception->getMessage())
            ->and($response->getData()->exception->file)->toEqual($exception->getFile())
            ->and($response->getData()->exception->line)->toEqual($exception->getLine())
            ->and($response->getData()->exception->code)->toEqual($exception->getCode());
    }
});

it('can respond with errors', function () {
    $message = 'Missing required parameters';
    $statusCode = 406;

    $response = LaravelApi::errorResponse($message, $statusCode);
    expect($response)->toBeObject()
        ->and($response->getData())->toBeObject()
        ->and($response->getData()->success)->toBeFalse();
});

it('can respond with created', function () {
    $data = [
        'id' => 1,
        'name' => 'Test',
    ];

    $response = LaravelApi::createdResponse($data);
    expect($response)->toBeObject()
        ->and($response->getData())->toBeObject()
        ->and($response->getData()->success)->toBeTrue();
});
