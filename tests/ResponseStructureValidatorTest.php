<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Helpers\ResponseStructureValidator;

covers(ResponseStructureValidator::class);

beforeEach(function () {
    $this->validator = new ResponseStructureValidator();
});

it('validates a complete structure successfully', function () {
    $structure = [
        'success_key' => 'success',
        'message_key' => 'message',
        'data_key' => 'data',
        'errors_key' => 'errors',
        'error_code_key' => 'error_code',
        'meta_key' => 'meta',
        'links_key' => 'links',
        'include_api_version' => true,
    ];

    $result = $this->validator->validate($structure);

    expect($result)->toBe($structure);
});

it('throws an exception when a required key is missing', function () {
    $structure = [
        'success_key' => 'success',
        'message_key' => 'message',
        // 'data_key' is missing
        'errors_key' => 'errors',
        'error_code_key' => 'error_code',
        'meta_key' => 'meta',
        'links_key' => 'links',
        'include_api_version' => true,
    ];

    $this->validator->validate($structure);
})->throws(InvalidArgumentException::class, 'Missing required keys in response structure configuration: data_key');

it('throws an exception when multiple required keys are missing', function () {
    $structure = [
        'success_key' => 'success',
        'message_key' => 'message',
        // 'data_key' and 'errors_key' are missing
        'error_code_key' => 'error_code',
        'meta_key' => 'meta',
        'links_key' => 'links',
        'include_api_version' => true,
    ];

    $this->validator->validate($structure);
})->throws(
    InvalidArgumentException::class,
    'Missing required keys in response structure configuration: data_key, errors_key'
);

it('validates structure with additional keys', function () {
    $structure = [
        'success_key' => 'success',
        'message_key' => 'message',
        'data_key' => 'data',
        'errors_key' => 'errors',
        'error_code_key' => 'error_code',
        'meta_key' => 'meta',
        'links_key' => 'links',
        'include_api_version' => true,
        'additional_key' => 'value',
    ];

    $result = $this->validator->validate($structure);

    expect($result)->toBe($structure);
});

it('validates structure with different key values', function () {
    $structure = [
        'success_key' => 'isSuccessful',
        'message_key' => 'responseMessage',
        'data_key' => 'responseData',
        'errors_key' => 'errorList',
        'error_code_key' => 'errorCodeIdentifier',
        'meta_key' => 'metadata',
        'links_key' => 'hyperlinks',
        'include_api_version' => false,
    ];

    $result = $this->validator->validate($structure);

    expect($result)->toBe($structure);
});
