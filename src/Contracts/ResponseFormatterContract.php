<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Contracts;

use DateTime;
use Symfony\Component\HttpFoundation\Response as LaravelApiResponse;
use Throwable;

interface ResponseFormatterContract
{
    public function format(mixed $data, int $statusCode, array $headers, ?string $resourceClass = null): array;

    public function responseException(Throwable $throwable): array;

    public function createResponse(array $formattedResponse, string $format): LaravelApiResponse;

    public function response(
        ?array $data,
        int $statusCode,
        array $headers = [],
        ?string $apiVersion = null
    ): LaravelApiResponse;

    public function fields(array $data, array $fields): array;

    public function generateETag(array $data): string;

    public function getNotModified(string $etag, DateTime $lastModified): bool;

    public function getLastModifiedDate(array $data): DateTime;
}
