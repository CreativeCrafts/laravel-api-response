<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Contracts;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as LaravelApiResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

interface ApiResponseContract
{
    public function successResponse(
        string $message = '',
        array $data = [],
        array $headers = [],
        int $statusCode = Response::HTTP_OK,
        array $links = []
    ): LaravelApiResponse;

    public function errorResponse(
        string $message,
        int $statusCode,
        ?Throwable $throwable = null,
        int $errorCode = 1,
        array $headers = [],
        array $links = []
    ): LaravelApiResponse;

    public function validationErrorResponse(
        array $errors,
        string $message = 'Validation failed',
        int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        array $headers = []
    ): LaravelApiResponse;

    public function paginatedResponse(
        array $data,
        string $message = '',
        array $headers = [],
        array $links = []
    ): LaravelApiResponse;

    public function streamResponse(
        callable $dataGenerator,
        string $message = '',
        array $headers = [],
        int $statusCode = Response::HTTP_OK
    ): StreamedResponse;

    public function metadataResponse(array $additionalInfo = [], array $headers = []): LaravelApiResponse;

    public function bulkOperationResponse(
        array $operations,
        string $message = '',
        array $headers = [],
        int $statusCode = Response::HTTP_OK
    ): LaravelApiResponse;

    public function partialResponse(
        array $data,
        array $fields,
        string $message = '',
        array $headers = [],
        int $statusCode = Response::HTTP_OK
    ): LaravelApiResponse;

    public function conditionalResponse(
        array $data,
        string $message = '',
        array $headers = [],
        int $statusCode = Response::HTTP_OK,
        array $links = []
    ): LaravelApiResponse;

    public function updateResponseStructure(array $newStructure): void;

    public function setErrorCodeMappings(array $mappings): void;

    public function getErrorCodeMapping(int $errorCode): ?array;
}
