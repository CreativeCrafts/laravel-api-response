<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Helpers\ResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

covers(ResponseFormatter::class);

beforeEach(function () {
    $this->formatter = new ResponseFormatter();
    $this->defaultStructure = [
        'success_key' => 'success',
        'message_key' => 'message',
        'data_key' => 'data',
        'errors_key' => 'errors',
        'error_code_key' => 'error_code',
        'meta_key' => 'meta',
        'links_key' => '_links',
        'include_api_version' => true,
    ];

    Config::set('api-response.response_structure', $this->defaultStructure);
});

it('formats basic response data correctly', function () {
    $data = ['key' => 'value'];
    $result = $this->formatter->format($data, 200, []);

    expect($result)->toBeArray()
        ->toHaveKey('content')
        ->and($result['content'])->toHaveKeys(['success', 'message', 'data'])
        ->and($result['statusCode'])->toBe(200)
        ->and($result['headers'])->toBeArray();
});

it('handles error responses', function () {
    $data = ['success' => false, 'message' => 'Error occurred', 'errors' => ['field' => 'Invalid']];
    $result = $this->formatter->format($data, 400, []);
    // fwrite(STDERR, "Result: " . print_r($result['content'], true) . "\n");

    expect($result['content'])->toHaveKey('error_code')
        ->and($result['content']['success'])->toBe(false)
        ->and($result['statusCode'])->toBe(400);
});

it('includes links when provided', function () {
    $data = ['data' => [], '_links' => ['self' => 'http://api.example.com']];
    $result = $this->formatter->format($data, 200, []);

    expect($result['content'])->toHaveKey('_links');
});

it('handles exceptions', function () {
    $exception = new Exception('Test exception');
    $result = $this->formatter->format(['exception' => $exception], 200, []);

    expect($result['statusCode'])->toBe(500);
});

it('formats exceptions correctly', function () {
    $exception = new Exception('Test exception', 100);
    $result = $this->formatter->responseException($exception);

    expect($result)->toHaveKeys(['message', 'file', 'line', 'code', 'trace'])
        ->and($result['message'])->toBe('Test exception')
        ->and($result['code'])->toBe(100);
});

it('creates JSON response', function () {
    $formattedResponse = [
        'content' => ['key' => 'value'],
        'statusCode' => 200,
        'headers' => ['X-Custom' => 'Test'],
    ];
    $response = $this->formatter->createResponse($formattedResponse, 'json');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Type'))->toContain('application/json')
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('X-Custom'))->toBe('Test');
});

it('creates XML response', function () {
    $formattedResponse = [
        'content' => ['key' => 'value'],
        'statusCode' => 200,
        'headers' => ['X-Custom' => 'Test'],
    ];
    $response = $this->formatter->createResponse($formattedResponse, 'xml');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('X-Custom'))->toBe('Test');
});

it('filters fields correctly', function () {
    $data = ['field1' => 'value1', 'field2' => 'value2', 'field3' => 'value3'];
    $fields = ['field1', 'field3'];
    $result = $this->formatter->fields($data, $fields);

    expect($result)->toHaveKeys(['field1', 'field3'])
        ->not->toHaveKey('field2');
});

it('generates ETag correctly', function () {
    $data = ['key' => 'value'];
    $etag = $this->formatter->generateETag($data);

    expect($etag)->toBeString()->not->toBeEmpty();
});

it('checks if resource is not modified', function () {
    $etag = 'test-etag';
    $lastModified = new DateTime();

    $this->app['request']->headers->set('If-None-Match', $etag);
    $this->app['request']->headers->set('If-Modified-Since', $lastModified->format(DateTime::RFC7231));

    $result = $this->formatter->getNotModified($etag, $lastModified);

    expect($result)->toBeTrue();
});

it('gets last modified date', function () {
    $data = ['updated_at' => '2023-01-01 00:00:00'];
    $result = $this->formatter->getLastModifiedDate($data);

    expect($result)->toBeInstanceOf(DateTime::class)
        ->and($result->format('Y-m-d'))->toBe('2023-01-01');
});

it('transforms data using API Resources', function () {
    $testResourceClass = new class ([]) extends JsonResource {
        public function toArray($request)
        {
            return [
                'transformed' => $this->resource['original'] ?? null
            ];
        }
    };

    $data = ['original' => true];
    $result = $this->formatter->format($data, 200, [], get_class($testResourceClass));
    // fwrite(STDERR, "Result: " . print_r($result, true) . "\n");

    expect($result)->toHaveKey('content')
        ->and($result['content'])->toHaveKey('data')
        ->and($result['content']['data'])->toHaveKey('transformed')
        ->and($result['content']['data']['transformed'])->toBeTrue();
});

it('handles paginated data', function () {
    $paginator = new LengthAwarePaginator(
        ['item1', 'item2'],
        2,
        1,
        1
    );

    $result = $this->formatter->format($paginator, 200, []);

    //fwrite(STDERR, "Result Content: " . print_r($result, true) . "\n");

    expect($result['content'])->toHaveKey('data')
        ->and($result['content'])->toHaveKey('meta');
});

it('compresses response when enabled', function () {
    Config::set('laravel-api-response.enable_compression', true);
    Config::set('laravel-api-response.compression_threshold', 10);

    $largeData = str_repeat('a', 1000);
    $response = $this->formatter->response(['data' => $largeData], 200);

    expect($response->headers->get('Content-Encoding'))->toBe('gzip');
});

it('does not compress small responses', function () {
    Config::set('laravel-api-response.enable_compression', true);
    Config::set('laravel-api-response.compression_threshold', 1000);

    $smallData = 'small data';
    $response = $this->formatter->response(['data' => $smallData], 200);

    expect($response->headers->has('Content-Encoding'))->toBeFalse();
});

it('adds API version when configured', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $this->app->instance('request', $request);
    Config::set('api-response.response_structure.include_api_version', true);

    $response = $this->formatter->response(['data' => 'test'], 200, [], '2.0');

    expect(json_decode($response->getContent(), true))->toHaveKey('api_version')
        ->and(json_decode($response->getContent(), true)['api_version'])->toBe('2.0');
});

it('handles various data types in XML conversion', function () {
    $data = [
        'string' => 'test',
        'integer' => 123,
        'boolean' => true,
        'null' => null,
        'array' => ['nested' => 'value'],
        'object' => new class () {
            public function __toString()
            {
                return 'object';
            }
        },
    ];

    $result = $this->formatter->createResponse(['content' => $data, 'statusCode' => 200, 'headers' => []], 'xml');

    expect($result->getContent())->toContain('<string>test</string>')
        ->toContain('<integer>123</integer>')
        ->toContain('<boolean>true</boolean>')
        ->toContain('<null/>')
        ->toContain('<array><nested>value</nested></array>')
        ->toContain('<object>object</object>');
});
