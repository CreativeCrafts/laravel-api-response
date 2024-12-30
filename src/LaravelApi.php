<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse;

use CreativeCrafts\LaravelApiResponse\Contracts\ApiResponseContract;
use CreativeCrafts\LaravelApiResponse\Helpers\HateoasLinkGenerator;
use CreativeCrafts\LaravelApiResponse\Helpers\LocalizationHelper;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseFormatter;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseStructureValidator;
use Exception;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as LaravelApiResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class LaravelApi implements ApiResponseContract
{
    private string $apiVersion;

    private array $responseStructure;

    private array $errorCodeMappings = [];

    public function __construct(
        private readonly ResponseFormatter $format,
        private readonly LocalizationHelper $localizationHelper,
        private readonly HateoasLinkGenerator $linkGenerator,
        private readonly ResponseStructureValidator $responseStructureValidator
    ) {
        $this->apiVersion = Config::string('api-response.api_version', '1.0');
        $this->responseStructure = $this->responseStructureValidator->validate(
            Config::array('api-response.response_structure', [])
        );
    }

    /**
     * Dynamically update the response structure at runtime.
     * This method allows changing the response structure keys after the initial configuration.
     * It validates the new structure and updates the existing one with the new values.
     *
     * @param array $newStructure An array containing the new structure keys to be updated.
     * @throws InvalidArgumentException If any of the new keys are invalid.
     */
    public function updateResponseStructure(array $newStructure): void
    {
        $validatedStructure = $this->responseStructureValidator->validate(
            array_merge($this->responseStructure, $newStructure)
        );

        $this->responseStructure = $validatedStructure;
    }

    /**
     * Generate a streamed response.
     * This method creates a StreamedResponse for scenarios where data needs to be streamed to the client.
     *
     * @param callable(): iterable $dataGenerator A callable that returns an iterable to be streamed
     * @param string $message An optional message to be included in the response
     * @param array $headers Any additional headers to be sent with the response
     * @param int $statusCode The HTTP status code for the response. Defaults to 200 OK.
     * @return StreamedResponse A StreamedResponse object that will stream the data to the client
     */
    public function streamResponse(
        callable $dataGenerator,
        string $message = '',
        array $headers = [],
        int $statusCode = Response::HTTP_OK
    ): StreamedResponse {
        $responseStructure = $this->responseStructure;
        $apiVersion = $this->apiVersion;
        $localizationHelper = $this->localizationHelper;

        return new StreamedResponse(
            function () use ($dataGenerator, $message, $responseStructure, $apiVersion, $localizationHelper): void {
                echo json_encode([
                        $responseStructure['success_key'] => true,
                        $responseStructure['message_key'] => $localizationHelper->localize($message),
                        'api_version' => $apiVersion,
                    ], JSON_THROW_ON_ERROR) . "\n";

                $data = $dataGenerator();
                if (!is_iterable($data)) {
                    throw new RuntimeException('Data generator must return an iterable.');
                }

                foreach ($data as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        echo json_encode([
                                $key => $value,
                            ], JSON_THROW_ON_ERROR) . "\n";
                    } elseif (is_array($value) || is_object($value)) {
                        echo json_encode($value, JSON_THROW_ON_ERROR) . "\n";
                    }
                    ob_flush();
                    flush();
                }
            },
            $statusCode,
            array_merge($headers, [
                'Content-Type' => 'application/json',
            ])
        );
    }

    /**
     * Generate a success response with optional message, data, headers, and status code.
     * This method creates a response indicating a successful operation.
     * It includes a success flag, a localized message, any provided data, and allows for a custom status code.
     *
     * @param string $message The message to be included in the response. This will be localized.
     * @param array $data Any additional data to be included in the response.
     * @param array $headers Any additional headers to be sent with the response.
     * @param int $statusCode The HTTP status code for the response. Defaults to 200 OK.
     * @param array<string, string|array{route: string, params?: array}> $links Optional. An array of HATEOAS links to be included in the response.
     * @return LaravelApiResponse A response object with the success data and specified HTTP status.
     * @throws Exception
     */
    public function successResponse(
        string $message = '',
        array $data = [],
        array $headers = [],
        int $statusCode = Response::HTTP_OK,
        array $links = []
    ): LaravelApiResponse {
        $responseData = [
            $this->responseStructure['success_key'] => true,
            $this->responseStructure['message_key'] => $this->localizationHelper->localize($message),
            $this->responseStructure['data_key'] => $data,
        ];

        if ($links !== []) {
            $responseData[$this->responseStructure['links_key']] = $this->linkGenerator->generateLinks($links);
        }

        return $this->format->response($responseData, $statusCode, $headers, $this->apiVersion);
    }

    /**
     * Generate an error response with detailed information.
     * This method creates a JSON response for error scenarios, including a failure flag,
     * error message, error code, and optional exception details and HATEOAS links.
     *
     * @param string $message The error message to be included in the response.
     * @param int $statusCode The HTTP status code for the error response.
     * @param Throwable|null $throwable Optional. The exception object, if available.
     * @param int $errorCode The specific error code for this error. Defaults to 1.
     * @param array $headers Any additional headers to be sent with the response.
     * @param array<string, string|array{route: string, params?: array}> $links Optional. An array of HATEOAS links to be included in the response.
     * @return LaravelApiResponse A JSON response object with the error data and specified HTTP status code.
     * @throws Exception
     */
    public function errorResponse(
        string $message,
        int $statusCode,
        ?Throwable $throwable = null,
        int $errorCode = 1,
        array $headers = [],
        array $links = []
    ): LaravelApiResponse {
        $data = [
            $this->responseStructure['success_key'] => false,
            $this->responseStructure['message_key'] => $message,
            $this->responseStructure['error_code_key'] => $errorCode,
        ];

        if (isset($this->errorCodeMappings[$errorCode])) {
            $data['error_details'] = $this->errorCodeMappings[$errorCode];
        }

        if ($links !== []) {
            $data[$this->responseStructure['links_key']] = $this->linkGenerator->generateLinks($links);
        }

        if ($throwable instanceof Throwable && $this->shouldShowExceptionDetails()) {
            $data['exception'] = $this->format->responseException($throwable);
        }

        return $this->format->response($data, $statusCode, $headers, $this->apiVersion);
    }

    /**
     * Generate a validation error response.
     * This method creates a JSON response for validation error scenarios,
     * including a failure flag, error message, validation errors, and an error code.
     *
     * @param array $errors An array of validation errors.
     * @param string $message The error message to be included in the response. Defaults to 'Validation failed'.
     * @param int $statusCode The HTTP status code for the error response. Defaults to 422 (Unprocessable Entity).
     * @param array $headers Any additional headers to be sent with the response.
     * @return LaravelApiResponse A JSON response object with the validation error data and specified HTTP status code.
     * @throws Exception
     */
    public function validationErrorResponse(
        array $errors,
        string $message = 'Validation failed',
        int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        array $headers = []
    ): LaravelApiResponse {
        $data = [
            $this->responseStructure['success_key'] => false,
            $this->responseStructure['message_key'] => $message,
            $this->responseStructure['errors_key'] => $errors,
            $this->responseStructure['error_code_key'] => Response::HTTP_UNPROCESSABLE_ENTITY,
        ];

        return $this->format->response($data, $statusCode, $headers, $this->apiVersion);
    }

    /**
     * Generate a paginated response with optional message and caching.
     * This method creates a JSON response for paginated data, including
     * success flag, message, data, pagination metadata, and navigation links.
     * Caching is controlled via configuration.
     *
     * @param array $data An array containing paginated data and metadata.
     * @param string $message Optional. A message to be included in the response.
     * @param array $headers Any additional headers to be sent with the response.
     * @param array<string, string|array{route: string, params?: array}> $links Optional. An array of HATEOAS links to be included in the response.
     * @return LaravelApiResponse A JSON response object with the paginated data and metadata.
     * @throws Exception
     */
    public function paginatedResponse(
        array $data,
        string $message = '',
        array $headers = [],
        array $links = []
    ): LaravelApiResponse {
        $responseData = [
            $this->responseStructure['success_key'] => true,
            $this->responseStructure['message_key'] => $this->localizationHelper->localize($message),
            $this->responseStructure['data_key'] => $data['data'],
            $this->responseStructure['meta_key'] => [
                'current_page' => $data['current_page'],
                'from' => $data['from'],
                'last_page' => $data['last_page'],
                'per_page' => $data['per_page'],
                'to' => $data['to'],
                'total' => $data['total'],
            ],
        ];

        $generatedLinks = array_merge([
            'first' => isset($data['first_page_url']) ? $this->linkGenerator->generate(
                fluent($data)->string('first_page_url')->toString(),
                [],
                'first'
            ) : null,
            'last' => isset($data['last_page_url']) ? $this->linkGenerator->generate(
                fluent($data)->string('last_page_url')->toString(),
                [],
                'last'
            ) : null,
            'prev' => isset($data['prev_page_url']) ? $this->linkGenerator->generate(
                fluent($data)->string('prev_page_url')->toString(),
                [],
                'prev'
            ) : null,
            'next' => isset($data['next_page_url']) ? $this->linkGenerator->generate(
                fluent($data)->string('next_page_url')->toString(),
                [],
                'next'
            ) : null,
        ], $this->linkGenerator->generateLinks($links));

        $responseData[$this->responseStructure['links_key']] = $generatedLinks;

        $rateLimitKey = 'api_rate_limit:' . request()->ip();
        $maxAttempts = Config::integer('api-response.rate_limit_max_attempts', 60);
        $decayMinutes = Config::integer('api-response.rate_limit_decay_minutes', 1);

        $headers = array_merge($headers, [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($rateLimitKey, $maxAttempts),
            'X-RateLimit-Reset' => RateLimiter::availableIn($rateLimitKey),
        ]);

        $rateLimited = !RateLimiter::attempt(
            $rateLimitKey,
            $maxAttempts,
            static function (): true {
                return true;
            },
            $decayMinutes * 60
        );

        if ($rateLimited) {
            return $this->errorResponse(
                'Too Many Requests',
                Response::HTTP_TOO_MANY_REQUESTS,
                null,
                Response::HTTP_TOO_MANY_REQUESTS,
                $headers
            );
        }

        if (Config::boolean('api-response.cache_paginated_responses', false)) {
            $cachePrefix = Config::string('api-response.paginated_cache_prefix', 'laravel_api_paginated_');
            $cacheDuration = Config::integer('api-response.paginated_cache_duration', 3600);
            $cacheKey = $cachePrefix . hash('sha256', serialize($data) . serialize($headers));

            $cachedResponse = Cache::remember(
                $cacheKey,
                $cacheDuration,
                function () use ($responseData, $headers): LaravelApiResponse {
                    return $this->format->response($responseData, Response::HTTP_OK, $headers, $this->apiVersion);
                }
            );
            if ($cachedResponse instanceof LaravelApiResponse) {
                return $cachedResponse;
            }
        }

        return $this->format->response($responseData, Response::HTTP_OK, $headers, $this->apiVersion);
    }

    /**
     * Apply compression to the response if enabled in the configuration.
     */
    public function applyCompression(Router $router): void
    {
        if (Config::boolean('api-response.enable_compression', true)) {
            $router->pushMiddlewareToGroup('api', SetCacheHeaders::class);
            $router->pushMiddlewareToGroup('api', SubstituteBindings::class);
        }
    }

    /**
     * Generate a metadata response with information about API endpoints.
     *
     * @param array $additionalInfo Optional. Additional information to include in the metadata.
     * @param array $headers Any additional headers to be sent with the response.
     * @return LaravelApiResponse A JSON response object with the API metadata and HTTP 200 OK status.
     * @throws Exception
     */
    public function metadataResponse(array $additionalInfo = [], array $headers = []): LaravelApiResponse
    {
        /** @var RouteCollection $routes */
        $routes = Route::getRoutes();
        $endpoints = [];

        foreach ($routes->getIterator() as $route) {
            /** @var \Illuminate\Routing\Route $route */
            if (str_starts_with($route->uri(), 'api')) {
                $endpoints[] = [
                    'uri' => $route->uri(),
                    'methods' => $route->methods(),
                    'name' => $route->getName(),
                ];
            }
        }

        $metadata = [
            'version' => $this->apiVersion,
            'endpoints' => $endpoints,
            'additional_info' => $additionalInfo,
        ];

        $responseData = [
            $this->responseStructure['success_key'] => true,
            $this->responseStructure['message_key'] => 'API Metadata',
            $this->responseStructure['data_key'] => $metadata,
        ];

        return $this->format->response($responseData, Response::HTTP_OK, $headers, $this->apiVersion);
    }

    /**
     * Set the error code mappings.
     *
     * @param array<int, array> $mappings An array of error code mappings.
     */
    public function setErrorCodeMappings(array $mappings): void
    {
        $this->errorCodeMappings = $mappings;
    }

    /**
     * Get the mapping for a specific error code.
     *
     * @param int $errorCode The error code to look up.
     * @return array|null The error mapping if found, null otherwise.
     */
    public function getErrorCodeMapping(int $errorCode): ?array
    {
        $mapping = $this->errorCodeMappings[$errorCode] ?? null;
        return is_array($mapping) ? $mapping : null;
    }

    /**
     * Generate a response for bulk operations.
     * This method creates a JSON response for multiple operations performed in a single request.
     * It includes an overall success status, a message, and detailed results for each operation.
     *
     * @param array<int, array{success?: bool, message?: string, data?: mixed, error_code?: int}> $operations An array of operation results
     * @param string $message An optional overall message for the bulk operation
     * @param array $headers Any additional headers to be sent with the response
     * @param int $statusCode The HTTP status code for the response. Defaults to 200 OK.
     * @return LaravelApiResponse A JSON response object with the bulk operation results
     * @throws Exception
     */
    public function bulkOperationResponse(
        array $operations,
        string $message = '',
        array $headers = [],
        int $statusCode = Response::HTTP_OK
    ): LaravelApiResponse {
        $overallSuccess = true;
        $formattedOperations = [];

        foreach ($operations as $index => $operation) {
            if (!is_array($operation)) {
                continue;
            }

            $formattedOperation = [
                $this->responseStructure['success_key'] => $operation['success'] ?? false,
                $this->responseStructure['message_key'] => $this->localizationHelper->localize(
                    $operation['message'] ?? ''
                ),
            ];

            if (isset($operation['data'])) {
                $formattedOperation[$this->responseStructure['data_key']] = $operation['data'];
            }

            if (isset($operation['error_code'])) {
                $formattedOperation[$this->responseStructure['error_code_key']] = $operation['error_code'];
            }

            $formattedOperations[$index] = $formattedOperation;

            if (!($operation['success'] ?? false)) {
                $overallSuccess = false;
            }
        }

        $responseData = [
            $this->responseStructure['success_key'] => $overallSuccess,
            $this->responseStructure['message_key'] => $this->localizationHelper->localize($message),
            'operations' => $formattedOperations,
        ];

        return $this->format->response($responseData, $statusCode, $headers, $this->apiVersion);
    }

    /**
     * Generate a partial response with only requested fields.
     * This method creates a JSON response containing only the fields specified by the client.
     *
     * @param array $data The full data array
     * @param array $fields The fields requested by the client
     * @param string $message An optional message to be included in the response
     * @param array $headers Any additional headers to be sent with the response
     * @param int $statusCode The HTTP status code for the response. Defaults to 200 OK.
     * @return LaravelApiResponse A JSON response object with the partial data
     * @throws Exception
     */
    public function partialResponse(
        array $data,
        array $fields,
        string $message = '',
        array $headers = [],
        int $statusCode = Response::HTTP_OK
    ): LaravelApiResponse {
        $filteredData = $this->format->fields($data, $fields);

        $responseData = [
            $this->responseStructure['success_key'] => true,
            $this->responseStructure['message_key'] => $this->localizationHelper->localize($message),
            $this->responseStructure['data_key'] => $filteredData,
        ];

        return $this->format->response($responseData, $statusCode, $headers, $this->apiVersion);
    }

    /**
     * Generate a conditional response with ETag and Last-Modified headers.
     * This method creates a JSON response that supports conditional requests.
     *
     * @param array $data The data to be included in the response
     * @param string $message An optional message to be included in the response
     * @param array $headers Any additional headers to be sent with the response
     * @param int $statusCode The HTTP status code for the response. Defaults to 200 OK.
     * @param array<string, string|array{route: string, params?: array}> $links Optional. An array of HATEOAS links to be included in the response.
     * @return LaravelApiResponse A JSON response object with conditional headers
     * @throws Exception
     */
    public function conditionalResponse(
        array $data,
        string $message = '',
        array $headers = [],
        int $statusCode = Response::HTTP_OK,
        array $links = []
    ): LaravelApiResponse {
        $responseData = [
            $this->responseStructure['success_key'] => true,
            $this->responseStructure['message_key'] => $this->localizationHelper->localize($message),
            $this->responseStructure['data_key'] => $data,
        ];

        if ($links !== []) {
            $responseData[$this->responseStructure['links_key']] = $this->linkGenerator->generateLinks($links);
        }

        $etag = $this->format->generateETag($responseData);
        $lastModified = $this->format->getLastModifiedDate($data);

        if ($this->format->getNotModified($etag, $lastModified)) {
            return $this->format->response(null, Response::HTTP_NOT_MODIFIED, $headers, $this->apiVersion);
        }

        $headers = array_merge($headers, [
            'ETag' => '"' . $etag . '"',
            'Last-Modified' => $lastModified->format('D, d M Y H:i:s') . ' GMT',
            'Cache-Control' => 'private, must-revalidate',
        ]);

        return $this->format->response($responseData, $statusCode, $headers, $this->apiVersion);
    }

    /**
     * Determine if exception details should be shown in the response.
     * This method checks if the current application environment is in the list of
     * allowed environments for showing exception details. The allowed environments
     * are configurable via the 'api-response.show_exception_environments' config.
     *
     * @return bool Returns true if the current environment is allowed to show
     *              exception details, false otherwise.
     */
    protected function shouldShowExceptionDetails(): bool
    {
        $allowedEnvironments = Config::array(
            'api-response.show_exception_environments',
            ['local', 'development']
        );
        return in_array(Config::string('app.env'), $allowedEnvironments, true);
    }
}
