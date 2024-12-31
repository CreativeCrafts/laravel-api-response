<?php

use CreativeCrafts\LaravelApiResponse\Contracts\HateoasLinkGeneratorContract;
use CreativeCrafts\LaravelApiResponse\Contracts\LocalizationHelperContract;
use CreativeCrafts\LaravelApiResponse\Contracts\ResponseFormatterContract;
use CreativeCrafts\LaravelApiResponse\Helpers\HateoasLinkGenerator;
use CreativeCrafts\LaravelApiResponse\Helpers\LocalizationHelper;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseFormatter;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseStructureValidator;
use CreativeCrafts\LaravelApiResponse\LaravelApi;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

covers(LaravelApi::class);

beforeEach(function () {
    $this->responseFormatter = new ResponseFormatter();
    $this->localizationHelper = new LocalizationHelper();
    $this->linkGenerator = new HateoasLinkGenerator();
    $this->responseStructureValidator = new ResponseStructureValidator();

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
    Config::set('api-response.api_version', '1.0');
    Config::set('api-response.rate_limit_max_attempts', 60);
    Config::set('api-response.rate_limit_decay_minutes', 1);
    Config::set('api-response.cache_paginated_responses', false);

    $this->api = new LaravelApi(
        $this->responseFormatter,
        $this->localizationHelper,
        $this->linkGenerator,
        $this->responseStructureValidator
    );
});

describe('updateResponseStructure', function () {
    it('updates the response structure with valid keys', function () {
        $newStructure = [
            'new_key' => 'new_value',
        ];
        $mergedNewStructure = array_merge($this->defaultStructure, $newStructure);

        $this->api->updateResponseStructure($mergedNewStructure);

        $reflection = new ReflectionClass($this->api);
        $property = $reflection->getProperty('responseStructure');
        $property->setAccessible(true);

        expect($property->getValue($this->api))->toBe($mergedNewStructure);
    });

    it('throws an exception for missing required keys', function () {
        $incompleteStructure = [
            'success_key' => 'success',
            'message_key' => 'message',
            'data_key' => 'data',
            'error_code_key' => 'error_code',
            'meta_key' => 'meta',
            'links_key' => '_links',
            'include_api_version' => true,
            'custom_key' => 'custom_value'
        ];

        expect(fn() => $this->api->updateResponseStructure($incompleteStructure))
            ->toThrow(
                InvalidArgumentException::class,
                'Missing required keys in response structure configuration: errors_key'
            );
    });
});

describe('streamResponse', function () {
    it('returns a StreamedResponse', function () {
        $dataGenerator = function () {
            yield 'key' => 'value';
        };

        $response = $this->api->streamResponse($dataGenerator);

        expect($response)->toBeInstanceOf(StreamedResponse::class);
    });

    it('throws an exception when data generator does not return an iterable', function () {
        $dataGenerator = function () {
            return 'not iterable';
        };

        expect(function () use ($dataGenerator) {
            $response = $this->api->streamResponse($dataGenerator);
            $response->sendContent();
        })->toThrow(RuntimeException::class, 'Data generator must return an iterable.');
    });
});

describe('successResponse', function () {
    it('returns a success response with correct structure', function () {
        $response = $this->api->successResponse('Test message', ['data' => 'value']);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(200);

        $content = json_decode($response->getContent(), true);

        expect($content)->toBeArray()
            ->toHaveKey('success')
            ->toHaveKey('message')
            ->toHaveKey('data')
            ->and($content['success'])->toBeTrue()
            ->and($content['message'])->toBe('Test message')
            ->and($content['data'])->toBe(['data' => 'value']);
    });

    it('includes HATEOAS links when provided', function () {
        $mockLinkGenerator = Mockery::mock(HateoasLinkGeneratorContract::class);
        $mockLinkGenerator->shouldReceive('generateLinks')
            ->once()
            ->with(['self' => 'resource'])
            ->andReturn([
                'self' => [
                    'href' => 'https://example.com/resource',
                    'method' => 'GET'
                ]
            ]);

        $api = new LaravelApi(
            $this->responseFormatter,
            $this->localizationHelper,
            $mockLinkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->successResponse('Test message', [], [], 200, ['self' => 'resource']);

        expect($response)->toBeInstanceOf(Response::class);
        $content = json_decode($response->getContent(), true);

        // fwrite(STDERR, "Response Content: " . print_r($content, true) . "\n");
        $linksKey = Config::get('api-response.response_structure.links_key', '_links');
        expect($content)->toHaveKey($linksKey)
            ->and($content[$linksKey])->toHaveKey('self')
            ->and($content[$linksKey]['self'])->toHaveKey(
                'href',
                'https://example.com/resource'
            );
    });
});

describe('errorResponse', function () {
    it('returns an error response with correct structure', function () {
        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('response')->andReturn(new Response());

        $api = new LaravelApi(
            $mockResponseFormatter,
            $this->localizationHelper,
            $this->linkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->errorResponse('Error occurred', 400);

        expect($response)->toBeInstanceOf(Response::class);
    });

    it('includes exception details when in development environment', function () {
        Config::set('api-response.app.env', 'development');
        Config::set('api-response.show_exception_environments', ['local', 'development']);

        $exceptionDetails = ['exception' => 'details'];
        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('responseException')
            ->andReturn($exceptionDetails);
        $mockResponseFormatter->shouldReceive('response')
            ->andReturn(
                new Response(json_encode([
                    'error' => 'Error occurred',
                    'exception' => $exceptionDetails
                ]), 500, ['Content-Type' => 'application/json'])
            );

        $api = new LaravelApi(
            $mockResponseFormatter,
            $this->localizationHelper,
            $this->linkGenerator,
            $this->responseStructureValidator
        );

        $exception = new Exception('Test exception');
        $response = $api->errorResponse('Error occurred', 500, $exception);

        expect($response)->toBeInstanceOf(Response::class);

        $content = json_decode($response->getContent(), true);
        expect($content)->toHaveKey('exception')
            ->and($content['exception'])->toBe($exceptionDetails);
    });
});

describe('validationErrorResponse', function () {
    it('returns a validation error response with correct structure', function () {
        $validationErrors = ['field' => 'The field is required'];
        $expectedResponse = [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validationErrors,
            'error_code' => 422
        ];

        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('response')
            ->withArgs(function ($data, $statusCode, $headers, $apiVersion) use ($expectedResponse) {
                return $data === $expectedResponse &&
                    $statusCode === 422 &&
                    $headers === [] &&
                    $apiVersion === '1.0';
            })
            ->andReturn(new Response(json_encode($expectedResponse), 422, ['Content-Type' => 'application/json']));

        $api = new LaravelApi(
            $mockResponseFormatter,
            $this->localizationHelper,
            $this->linkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->validationErrorResponse($validationErrors);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(422);

        $content = json_decode($response->getContent(), true);
        expect($content)->toBe($expectedResponse)
            ->and($content)->toHaveKey('success')->and($content['success'])->toBeFalse()
            ->and($content)->toHaveKey('message')->and($content['message'])->toBe('Validation failed')
            ->and($content)->toHaveKey('errors')->and($content['errors'])->toBe($validationErrors)
            ->and($content)->toHaveKey('error_code')->and($content['error_code'])->toBe(422);
    });
});

describe('paginatedResponse', function () {
    it('returns a paginated response with correct structure', function () {
        $paginatedData = [
            'data' => [['id' => 1, 'name' => 'Item 1'], ['id' => 2, 'name' => 'Item 2']],
            'current_page' => 1,
            'from' => 1,
            'last_page' => 2,
            'per_page' => 2,
            'to' => 2,
            'total' => 3,
            'first_page_url' => 'https://example.com/api/items?page=1',
            'last_page_url' => 'https://example.com/api/items?page=2',
            'next_page_url' => 'https://example.com/api/items?page=2',
            'prev_page_url' => null,
        ];

        $expectedResponse = [
            'success' => true,
            'message' => 'Localized message',
            'data' => $paginatedData['data'],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 2,
                'per_page' => 2,
                'to' => 2,
                'total' => 3,
            ],
            '_links' => [
                'first' => ['href' => 'https://example.com/api/items?page=1', 'rel' => 'first'],
                'last' => ['href' => 'https://example.com/api/items?page=2', 'rel' => 'last'],
                'prev' => null,
                'next' => ['href' => 'https://example.com/api/items?page=2', 'rel' => 'next'],
            ],
        ];

        $mockLocalizationHelper = Mockery::mock(LocalizationHelperContract::class);
        $mockLocalizationHelper->shouldReceive('localize')->andReturn('Localized message');

        $mockLinkGenerator = Mockery::mock(HateoasLinkGeneratorContract::class);
        $mockLinkGenerator->shouldReceive('generate')
            ->andReturnUsing(function ($url, $params, $rel) {
                return ['href' => $url, 'rel' => $rel];
            });
        $mockLinkGenerator->shouldReceive('generateLinks')
            ->andReturn([]);

        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('response')
            ->withArgs(function ($data, $statusCode, $headers, $apiVersion) use ($expectedResponse) {
                return $data === $expectedResponse &&
                    $statusCode === 200 &&
                    isset($headers['X-RateLimit-Limit']) &&
                    isset($headers['X-RateLimit-Remaining']) &&
                    isset($headers['X-RateLimit-Reset']) &&
                    $apiVersion === '1.0';
            })
            ->andReturn(new Response(json_encode($expectedResponse), 200, ['Content-Type' => 'application/json']));

        RateLimiter::shouldReceive('attempt')->andReturn(true);
        RateLimiter::shouldReceive('remaining')->andReturn(59);
        RateLimiter::shouldReceive('availableIn')->andReturn(60);

        $api = new LaravelApi(
            $mockResponseFormatter,
            $mockLocalizationHelper,
            $mockLinkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->paginatedResponse($paginatedData);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(200);

        $content = json_decode($response->getContent(), true);
        expect($content)->toBe($expectedResponse);
    });

    it('returns a rate limited response when limit is exceeded', function () {
        $paginatedData = [
            'data' => [['id' => 1, 'name' => 'Item 1']],
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 1,
            'to' => 1,
            'total' => 1,
        ];

        $mockLocalizationHelper = Mockery::mock(LocalizationHelperContract::class);
        $mockLocalizationHelper->shouldReceive('localize')
            ->with('Too Many Requests')
            ->andReturn('Too Many Requests');
        $mockLocalizationHelper->shouldReceive('localize')
            ->with('')
            ->andReturn('');

        $mockLinkGenerator = Mockery::mock(HateoasLinkGeneratorContract::class);
        $mockLinkGenerator->shouldReceive('generateLinks')
            ->andReturn([]);

        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('response')
            ->withArgs(function ($data, $statusCode, $headers) {
                return $data['success'] === false &&
                    $data['message'] === 'Too Many Requests' &&
                    $statusCode === 429 &&
                    isset($headers['X-RateLimit-Limit']) &&
                    isset($headers['X-RateLimit-Remaining']) &&
                    isset($headers['X-RateLimit-Reset']);
            })
            ->andReturn(
                new Response(json_encode(['error' => 'Too Many Requests']), 429, ['Content-Type' => 'application/json'])
            );

        RateLimiter::shouldReceive('attempt')->andReturn(false);
        RateLimiter::shouldReceive('remaining')->andReturn(0);
        RateLimiter::shouldReceive('availableIn')->andReturn(60);

        $api = new LaravelApi(
            $mockResponseFormatter,
            $mockLocalizationHelper,
            $mockLinkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->paginatedResponse($paginatedData);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(429);

        $content = json_decode($response->getContent(), true);
        expect($content)->toHaveKey('error')
            ->and($content['error'])->toBe('Too Many Requests');
    });

    it('caches paginated responses when enabled', function () {
        Config::set('api-response.cache_paginated_responses', true);
        Config::set('api-response.paginated_cache_prefix', 'api_paginated_');
        Config::set('api-response.paginated_cache_duration', 3600);

        $paginatedData = [
            'data' => [['id' => 1, 'name' => 'Item 1']],
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 1,
            'to' => 1,
            'total' => 1,
            'first_page_url' => 'https://example.com/api/items?page=1',
            'last_page_url' => 'https://example.com/api/items?page=1',
            'next_page_url' => null,
            'prev_page_url' => null,
        ];

        $expectedResponse = [
            'success' => true,
            'message' => 'Localized message',
            'data' => $paginatedData['data'],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'per_page' => 1,
                'to' => 1,
                'total' => 1,
            ],
            '_links' => [
                'first' => ['href' => 'https://example.com/api/items?page=1', 'rel' => 'first'],
                'last' => ['href' => 'https://example.com/api/items?page=1', 'rel' => 'last'],
                'prev' => null,
                'next' => null,
            ],
        ];

        RateLimiter::shouldReceive('attempt')->andReturn(true);
        RateLimiter::shouldReceive('remaining')->andReturn(59);
        RateLimiter::shouldReceive('availableIn')->andReturn(60);

        $mockLocalizationHelper = Mockery::mock(LocalizationHelperContract::class);
        $mockLocalizationHelper->shouldReceive('localize')
            ->with('Localized message')
            ->andReturn('Localized message');

        $mockLinkGenerator = Mockery::mock(HateoasLinkGeneratorContract::class);
        $mockLinkGenerator->shouldReceive('generate')
            ->andReturnUsing(function ($url, $params, $rel) {
                return ['href' => $url, 'rel' => $rel];
            });
        $mockLinkGenerator->shouldReceive('generateLinks')
            ->andReturn([]);

        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('response')
            ->withArgs(function ($data, $statusCode, $headers, $apiVersion) use ($expectedResponse) {
                return $data === $expectedResponse &&
                    $statusCode === 200 &&
                    isset($headers['X-RateLimit-Limit']) &&
                    isset($headers['X-RateLimit-Remaining']) &&
                    isset($headers['X-RateLimit-Reset']) &&
                    $apiVersion === '1.0';
            })
            ->andReturn(new Response(json_encode($expectedResponse), 200, ['Content-Type' => 'application/json']));

        $cachedResponse = new Response(json_encode($expectedResponse), 200, ['Content-Type' => 'application/json']);
        Cache::shouldReceive('remember')
            ->withArgs(function ($key, $duration, $callback) {
                return str_starts_with($key, 'api_paginated_') && $duration === 3600 && is_callable($callback);
            })
            ->andReturn($cachedResponse);

        $api = new LaravelApi(
            $mockResponseFormatter,
            $mockLocalizationHelper,
            $mockLinkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->paginatedResponse($paginatedData, 'Localized message');

        expect($response)->toBe($cachedResponse)
            ->and($response->getStatusCode())->toBe(200);

        $content = json_decode($response->getContent(), true);
        expect($content)->toBe($expectedResponse);
    });
});

describe('metadataResponse', function () {
    it('generates correct metadata response', function () {
        $mockRouteFacade = Mockery::mock('alias:' . RouteFacade::class);
        $mockRouteCollection = Mockery::mock(RouteCollection::class);

        $mockRoute1 = Mockery::mock(Route::class);
        $mockRoute1->shouldReceive('uri')->andReturn('api/users');
        $mockRoute1->shouldReceive('methods')->andReturn(['GET', 'POST']);
        $mockRoute1->shouldReceive('getName')->andReturn('users.index');

        $mockRoute2 = Mockery::mock(Route::class);
        $mockRoute2->shouldReceive('uri')->andReturn('api/posts');
        $mockRoute2->shouldReceive('methods')->andReturn(['GET']);
        $mockRoute2->shouldReceive('getName')->andReturn('posts.index');

        $mockRoute3 = Mockery::mock(Route::class);
        $mockRoute3->shouldReceive('uri')->andReturn('web/home');

        $mockRouteCollection->shouldReceive('getRoutes')->andReturn([$mockRoute1, $mockRoute2, $mockRoute3]);

        $mockRouteFacade->shouldReceive('getRoutes')->andReturn($mockRouteCollection);

        $expectedMetadata = [
            'success' => true,
            'message' => 'API Metadata',
            'data' => [
                'version' => '1.0',
                'endpoints' => [
                    [
                        'uri' => 'api/users',
                        'methods' => ['GET', 'POST'],
                        'name' => 'users.index'
                    ],
                    [
                        'uri' => 'api/posts',
                        'methods' => ['GET'],
                        'name' => 'posts.index'
                    ]
                ],
                'additional_info' => ['custom' => 'info']
            ]
        ];

        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('response')
            ->withArgs(function ($data, $statusCode, $headers, $apiVersion) use ($expectedMetadata) {
                return $data === $expectedMetadata &&
                    $statusCode === 200 &&
                    $headers === [] &&
                    $apiVersion === '1.0';
            })
            ->andReturn(new Response(json_encode($expectedMetadata), 200, ['Content-Type' => 'application/json']));

        $laravelApi = new LaravelApi(
            $mockResponseFormatter,
            Mockery::mock(LocalizationHelperContract::class),
            Mockery::mock(HateoasLinkGeneratorContract::class),
            $this->responseStructureValidator
        );

        $reflectionClass = new ReflectionClass($laravelApi);

        $responseStructureProperty = $reflectionClass->getProperty('responseStructure');
        $responseStructureProperty->setAccessible(true);
        $responseStructureProperty->setValue($laravelApi, [
            'success_key' => 'success',
            'message_key' => 'message',
            'data_key' => 'data',
        ]);

        $apiVersionProperty = $reflectionClass->getProperty('apiVersion');
        $apiVersionProperty->setAccessible(true);
        $apiVersionProperty->setValue($laravelApi, '1.0');

        $result = $laravelApi->metadataResponse(['custom' => 'info']);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->getStatusCode())->toBe(200)
            ->and(json_decode($result->getContent(), true))->toBe($expectedMetadata);

        $mockResponseFormatter->shouldHaveReceived('response')->once();
    });
});

describe('bulkOperationResponse', function () {
    it('returns a bulk operation response with correct structure', function () {
        $operations = [
            [
                'success' => true,
                'message' => 'Operation 1 successful',
                'data' => ['id' => 1]
            ],
            [
                'success' => false,
                'message' => 'Operation 2 failed',
                'error_code' => 400
            ],
            [
                'success' => true,
                'message' => 'Operation 3 successful',
                'data' => ['id' => 3]
            ]
        ];

        $expectedResponse = [
            'success' => false, // Overall success is false because one operation failed
            'message' => 'Bulk operation completed',
            'operations' => [
                [
                    'success' => true,
                    'message' => 'Operation 1 successful',
                    'data' => ['id' => 1]
                ],
                [
                    'success' => false,
                    'message' => 'Operation 2 failed',
                    'error_code' => 400
                ],
                [
                    'success' => true,
                    'message' => 'Operation 3 successful',
                    'data' => ['id' => 3]
                ]
            ]
        ];

        $mockLocalizationHelper = Mockery::mock(LocalizationHelperContract::class);
        $mockLocalizationHelper->shouldReceive('localize')
            ->andReturnUsing(function ($message) {
                return $message; // For simplicity, just return the original message
            });

        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('response')
            ->withArgs(function ($data, $statusCode, $headers, $apiVersion) use ($expectedResponse) {
                return $data === $expectedResponse &&
                    $statusCode === 200 &&
                    $headers === [] &&
                    $apiVersion === '1.0';
            })
            ->andReturn(new Response(json_encode($expectedResponse), 200, ['Content-Type' => 'application/json']));

        $api = new LaravelApi(
            $mockResponseFormatter,
            $mockLocalizationHelper,
            $this->linkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->bulkOperationResponse($operations, 'Bulk operation completed');

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(200);

        $content = json_decode($response->getContent(), true);
        expect($content)->toBe($expectedResponse)
            ->and($content)->toHaveKey('success')->and($content['success'])->toBeFalse()
            ->and($content)->toHaveKey('message')->and($content['message'])->toBe('Bulk operation completed')
            ->and($content)->toHaveKey('operations')->and($content['operations'])->toBeArray()->toHaveCount(3);

        // Check each operation in the response
        foreach ($content['operations'] as $index => $operation) {
            expect($operation)->toHaveKey('success')->and($operation['success'])->toBe($operations[$index]['success'])
                ->and($operation)->toHaveKey('message')->and($operation['message'])->toBe(
                    $operations[$index]['message']
                );

            if (isset($operations[$index]['data'])) {
                expect($operation)->toHaveKey('data')->and($operation['data'])->toBe($operations[$index]['data']);
            }

            if (isset($operations[$index]['error_code'])) {
                expect($operation)->toHaveKey('error_code')->and($operation['error_code'])->toBe(
                    $operations[$index]['error_code']
                );
            }
        }
    });
});

describe('partialResponse', function () {
    it('returns a partial response with only specified fields', function () {
        $fullData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
            'address' => [
                'street' => '123 Main St',
                'city' => 'Anytown',
                'country' => 'USA'
            ]
        ];

        $fields = ['id', 'name', 'address.city'];

        $expectedPartialData = [
            'id' => 1,
            'name' => 'John Doe',
            'address' => [
                'city' => 'Anytown'
            ]
        ];

        $expectedResponse = [
            'success' => true,
            'message' => 'Partial data retrieved successfully',
            'data' => $expectedPartialData
        ];

        $mockLocalizationHelper = Mockery::mock(LocalizationHelperContract::class);
        $mockLocalizationHelper->shouldReceive('localize')
            ->with('Partial data retrieved successfully')
            ->andReturn('Partial data retrieved successfully');

        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('fields')
            ->with($fullData, $fields)
            ->andReturn($expectedPartialData);
        $mockResponseFormatter->shouldReceive('response')
            ->withArgs(function ($data, $statusCode, $headers, $apiVersion) use ($expectedResponse) {
                return $data === $expectedResponse &&
                    $statusCode === 200 &&
                    $headers === [] &&
                    $apiVersion === '1.0';
            })
            ->andReturn(new Response(json_encode($expectedResponse), 200, ['Content-Type' => 'application/json']));

        $api = new LaravelApi(
            $mockResponseFormatter,
            $mockLocalizationHelper,
            $this->linkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->partialResponse($fullData, $fields, 'Partial data retrieved successfully');

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(200);

        $content = json_decode($response->getContent(), true);
        expect($content)->toBe($expectedResponse)
            ->and($content)->toHaveKey('success')->and($content['success'])->toBeTrue()
            ->and($content)->toHaveKey('message')->and($content['message'])->toBe('Partial data retrieved successfully')
            ->and($content)->toHaveKey('data')->and($content['data'])->toBe($expectedPartialData);

        // Check that only specified fields are present in the response
        expect($content['data'])->toHaveKey('id')
            ->and($content['data'])->toHaveKey('name')
            ->and($content['data'])->toHaveKey('address')
            ->and($content['data']['address'])->toHaveKey('city');

        // Check that unspecified fields are not present in the response
        expect($content['data'])->not->toHaveKey('email')
            ->and($content['data'])->not->toHaveKey('age')
            ->and($content['data']['address'])->not->toHaveKey('street')
            ->and($content['data']['address'])->not->toHaveKey('country');
    });

    it('handles nested fields correctly', function () {
        $fullData = [
            'id' => 1,
            'name' => 'John Doe',
            'details' => [
                'age' => 30,
                'occupation' => 'Developer',
                'contact' => [
                    'email' => 'john@example.com',
                    'phone' => '1234567890'
                ]
            ]
        ];

        $fields = ['id', 'details.age', 'details.contact.email'];

        $expectedPartialData = [
            'id' => 1,
            'details' => [
                'age' => 30,
                'contact' => [
                    'email' => 'john@example.com'
                ]
            ]
        ];

        $expectedResponse = [
            'success' => true,
            'message' => 'Partial data retrieved successfully',
            'data' => $expectedPartialData
        ];

        $mockLocalizationHelper = Mockery::mock(LocalizationHelperContract::class);
        $mockLocalizationHelper->shouldReceive('localize')
            ->with('Partial data retrieved successfully')
            ->andReturn('Partial data retrieved successfully');

        $mockResponseFormatter = Mockery::mock(ResponseFormatterContract::class);
        $mockResponseFormatter->shouldReceive('response')
            ->withArgs(function ($data, $statusCode, $headers, $apiVersion) use ($expectedResponse) {
                return $data === $expectedResponse &&
                    $statusCode === 200 &&
                    $headers === [] &&
                    $apiVersion === '1.0';
            })
            ->andReturn(new Response(json_encode($expectedResponse), 200, ['Content-Type' => 'application/json']));
        $mockResponseFormatter->shouldReceive('fields')
            ->with($fullData, $fields)
            ->andReturn($expectedPartialData);

        $api = new LaravelApi(
            $mockResponseFormatter,
            $mockLocalizationHelper,
            $this->linkGenerator,
            $this->responseStructureValidator
        );

        $response = $api->partialResponse($fullData, $fields, 'Partial data retrieved successfully');

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(200);

        $content = json_decode($response->getContent(), true);
        expect($content)->toBe($expectedResponse)
            ->and($content['data'])->toBe($expectedPartialData);

        // Check that only specified nested fields are present in the response
        expect($content['data'])->toHaveKey('id')
            ->and($content['data'])->toHaveKey('details')
            ->and($content['data']['details'])->toHaveKey('age')
            ->and($content['data']['details'])->toHaveKey('contact')
            ->and($content['data']['details']['contact'])->toHaveKey('email');

        // Check that unspecified nested fields are not present in the response
        expect($content['data'])->not->toHaveKey('name')
            ->and($content['data']['details'])->not->toHaveKey('occupation')
            ->and($content['data']['details']['contact'])->not->toHaveKey('phone');
    });
});

describe('conditionalResponse', function () {
    it('returns 304 Not Modified when content has not changed', function () {
        $data = ['id' => 1, 'name' => 'Test'];
        $message = 'Test message';
        $etag = 'test-etag';
        $lastModified = new DateTime();

        $mockFormat = Mockery::mock(ResponseFormatterContract::class);
        $mockFormat->shouldReceive('generateETag')->andReturn($etag);
        $mockFormat->shouldReceive('getLastModifiedDate')->andReturn($lastModified);
        $mockFormat->shouldReceive('getNotModified')->andReturn(true);
        $mockFormat->shouldReceive('response')
            ->with(null, Response::HTTP_NOT_MODIFIED, [], '1.0')
            ->andReturn(new Response(null, Response::HTTP_NOT_MODIFIED));

        $mockLocalization = Mockery::mock(LocalizationHelperContract::class);
        $mockLocalization->shouldReceive('localize')->with($message)->andReturn('Localized message');

        $mockLinkGenerator = Mockery::mock(HateoasLinkGeneratorContract::class);

        $responseStructureValidator = new ResponseStructureValidator();

        $api = new LaravelApi(
            $mockFormat,
            $mockLocalization,
            $mockLinkGenerator,
            $responseStructureValidator
        );

        $response = $api->conditionalResponse($data, $message);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(Response::HTTP_NOT_MODIFIED);
    });

    it('returns 200 OK with data when content has changed', function () {
        $data = ['id' => 1, 'name' => 'Test'];
        $message = 'Test message';
        $etag = 'new-etag';
        $lastModified = new DateTime();

        $expectedResponse = [
            'success' => true,
            'message' => 'Localized message',
            'data' => $data
        ];

        $mockFormat = Mockery::mock(ResponseFormatterContract::class);
        $mockFormat->shouldReceive('generateETag')->andReturn($etag);
        $mockFormat->shouldReceive('getLastModifiedDate')->andReturn($lastModified);
        $mockFormat->shouldReceive('getNotModified')->andReturn(false);
        $mockFormat->shouldReceive('response')
            ->withArgs(
                function ($responseData, $statusCode, $headers, $apiVersion) use (
                    $expectedResponse,
                    $etag,
                    $lastModified
                ) {
                    return $responseData === $expectedResponse &&
                        $statusCode === Response::HTTP_OK &&
                        isset($headers['ETag']) && $headers['ETag'] === '"' . $etag . '"' &&
                        isset($headers['Last-Modified']) && $headers['Last-Modified'] === $lastModified->format(
                            'D, d M Y H:i:s'
                        ) . ' GMT' &&
                        isset($headers['Cache-Control']) && $headers['Cache-Control'] === 'private, must-revalidate' &&
                        $apiVersion === '1.0';
                }
            )
            ->andReturn(
                new Response(json_encode($expectedResponse), Response::HTTP_OK, [
                    'ETag' => '"' . $etag . '"',
                    'Last-Modified' => $lastModified->format('D, d M Y H:i:s') . ' GMT',
                    'Cache-Control' => 'private, must-revalidate',
                    'Content-Type' => 'application/json'
                ])
            );

        $mockLocalization = Mockery::mock(LocalizationHelperContract::class);
        $mockLocalization->shouldReceive('localize')->with($message)->andReturn('Localized message');

        $mockLinkGenerator = Mockery::mock(HateoasLinkGeneratorContract::class);
        $mockLinkGenerator->shouldReceive('generateLinks')->andReturn([]);

        $responseStructureValidator = new ResponseStructureValidator();

        $api = new LaravelApi(
            $mockFormat,
            $mockLocalization,
            $mockLinkGenerator,
            $responseStructureValidator
        );

        $response = $api->conditionalResponse($data, $message);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(Response::HTTP_OK);

        $content = json_decode($response->getContent(), true);
        expect($content)->toBe($expectedResponse)
            ->and($content['success'])->toBeTrue()
            ->and($content['message'])->toBe('Localized message')
            ->and($content['data'])->toBe($data);

        expect($response->headers->get('ETag'))->toBe('"' . $etag . '"')
            ->and($response->headers->get('Last-Modified'))->toBe($lastModified->format('D, d M Y H:i:s') . ' GMT')
            ->and($response->headers->get('Cache-Control'))->toBe('must-revalidate, private');
    });
});

describe('setErrorCodeMappings', function () {
    it('sets error code mappings correctly', function () {
        $mappings = [
            404 => ['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'],
            500 => ['message' => 'Server Error', 'code' => 'ERR_SERVER'],
        ];

        $this->api->setErrorCodeMappings($mappings);

        expect($this->api->getErrorCodeMapping(404))->toBe(['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'])
            ->and($this->api->getErrorCodeMapping(500))->toBe(['message' => 'Server Error', 'code' => 'ERR_SERVER']);
    });

    it('overwrites existing mappings', function () {
        $initialMappings = [
            404 => ['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'],
        ];

        $this->api->setErrorCodeMappings($initialMappings);

        $newMappings = [
            404 => ['message' => 'Resource Not Found', 'code' => 'ERR_RESOURCE_NOT_FOUND'],
        ];

        $this->api->setErrorCodeMappings($newMappings);

        expect($this->api->getErrorCodeMapping(404))->toBe(
            ['message' => 'Resource Not Found', 'code' => 'ERR_RESOURCE_NOT_FOUND']
        );
    });

    it('handles empty mappings array', function () {
        $this->api->setErrorCodeMappings([]);

        expect($this->api->getErrorCodeMapping(404))->toBeNull();
    });

    it('handles non-integer keys', function () {
        $mappings = [
            '404' => ['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'],
            'error' => ['message' => 'Generic Error', 'code' => 'ERR_GENERIC'],
        ];

        $this->api->setErrorCodeMappings($mappings);

        expect($this->api->getErrorCodeMapping(404))->toBe(['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'])
            ->and($this->api->getErrorCodeMapping(0))->toBeNull();
    });

    it('handles invalid mapping values', function () {
        $mappings = [
            404 => 'Not Found',
            500 => ['message' => 'Server Error', 'code' => 'ERR_SERVER'],
        ];

        $this->api->setErrorCodeMappings($mappings);

        expect($this->api->getErrorCodeMapping(404))->toBeNull()
            ->and($this->api->getErrorCodeMapping(500))->toBe(['message' => 'Server Error', 'code' => 'ERR_SERVER']);
    });
});

describe('getErrorCodeMapping', function () {
    it('returns correct mapping for existing error code', function () {
        $mappings = [
            404 => ['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'],
            500 => ['message' => 'Server Error', 'code' => 'ERR_SERVER'],
        ];

        $this->api->setErrorCodeMappings($mappings);

        expect($this->api->getErrorCodeMapping(404))->toBe(['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'])
            ->and($this->api->getErrorCodeMapping(500))->toBe(['message' => 'Server Error', 'code' => 'ERR_SERVER']);
    });

    it('returns null for non-existent error code', function () {
        $mappings = [
            404 => ['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'],
        ];

        $this->api->setErrorCodeMappings($mappings);

        expect($this->api->getErrorCodeMapping(500))->toBeNull();
    });

    it('handles empty mappings', function () {
        $this->api->setErrorCodeMappings([]);

        expect($this->api->getErrorCodeMapping(404))->toBeNull();
    });

    it('returns null for negative error codes', function () {
        $mappings = [
            404 => ['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'],
        ];

        $this->api->setErrorCodeMappings($mappings);

        expect($this->api->getErrorCodeMapping(-404))->toBeNull();
    });

    it('returns null for zero error code', function () {
        $mappings = [
            404 => ['message' => 'Not Found', 'code' => 'ERR_NOT_FOUND'],
        ];

        $this->api->setErrorCodeMappings($mappings);

        expect($this->api->getErrorCodeMapping(0))->toBeNull();
    });

    it('handles large error codes', function () {
        $largeErrorCode = PHP_INT_MAX;
        $mappings = [
            $largeErrorCode => ['message' => 'Large Error', 'code' => 'ERR_LARGE'],
        ];

        $this->api->setErrorCodeMappings($mappings);

        expect($this->api->getErrorCodeMapping($largeErrorCode))->toBe(
            ['message' => 'Large Error', 'code' => 'ERR_LARGE']
        );
    });
});

afterEach(function () {
    Mockery::close();
});