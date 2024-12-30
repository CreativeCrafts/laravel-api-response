<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Contracts;

interface LoggingContract
{
    public function logResponse(string $method, string $url, int $statusCode, array $responseData): void;
}
