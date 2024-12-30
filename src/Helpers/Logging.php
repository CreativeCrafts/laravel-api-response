<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Helpers;

use CreativeCrafts\LaravelApiResponse\Contracts\LoggingContract;
use Illuminate\Support\Facades\Log;
use JsonException;

final readonly class Logging implements LoggingContract
{
    /**
     * Log the API response.
     *
     * @param string $method The HTTP method of the request.
     * @param string $url The URL of the request.
     * @param int $statusCode The HTTP status code of the response.
     * @param array $responseData The data returned in the response.
     * @throws JsonException
     */
    public function logResponse(string $method, string $url, int $statusCode, array $responseData): void
    {
        $logMessage = sprintf(
            "API Response - Method: %s, URL: %s, Status: %d, Data: %s",
            $method,
            $url,
            $statusCode,
            json_encode($responseData, JSON_THROW_ON_ERROR)
        );

        if ($statusCode >= 400) {
            Log::error($logMessage);
        } else {
            Log::info($logMessage);
        }
    }
}
