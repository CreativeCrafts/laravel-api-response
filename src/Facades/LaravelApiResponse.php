<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Facades;

use CreativeCrafts\LaravelApiResponse\LaravelApi;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @method static Response successResponse(string $message = '', array $data = [], array $headers = [], int $statusCode = 200, array $links = [])
 * @method static Response errorResponse(string $message, int $statusCode, ?Throwable $throwable = null, int $errorCode = 1, array $headers = [], array $links = [])
 * @method static Response validationErrorResponse(array $errors, string $message = 'Validation failed', int $statusCode = 422, array $headers = [])
 * @method static Response paginatedResponse(array $data, string $message = '', array $headers = [], array $links = [])
 * @method static void applyCompression(Router $router)
 * @method static Response metadataResponse(array $additionalInfo = [], array $headers = [])
 * @method static void setErrorCodeMappings(array $mappings)
 * @method static ?array getErrorCodeMapping(int $errorCode)
 * @method static Response bulkOperationResponse(array $operations, string $message = '', array $headers = [], int $statusCode = 200)
 * @method static Response partialResponse(array $data, array $fields, string $message = '', array $headers = [], int $statusCode = 200)
 * @method static Response conditionalResponse(array $data, string $message = '', array $headers = [], int $statusCode = 200, array $links = [])
 * @method static StreamedResponse streamResponse(callable $dataGenerator, string $message = '', array $headers = [], int $statusCode = 200)
 * @method static void updateResponseStructure(array $newStructure)
 * @see LaravelApi
 * @mixin LaravelApi
 */
class LaravelApiResponse extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-api';
    }
}
