<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Helpers;

use CreativeCrafts\LaravelApiResponse\Contracts\ResponseFormatterContract;
use DateMalformedStringException;
use DateTime;
use Exception;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use JsonException;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as LaravelApiResponse;
use Throwable;

final readonly class ResponseFormatter implements ResponseFormatterContract
{
    /**
     * Prepare the response data for JSON output.
     *
     * @param mixed $data The input data to be processed.
     * @param int $statusCode The HTTP status code for the response.
     * @param array $headers Additional headers to be included in the response.
     * @param string|null $resourceClass The API Resource class to use for transformation.
     * @return array An associative array containing the formatted response.
     */
    public function format(mixed $data, int $statusCode, array $headers, ?string $resourceClass = null): array
    {
        $responseData = $this->transformData($data, $resourceClass);

        $response = [
            'success' => $responseData['success'] ?? true,
            'message' => $responseData['message'] ?? null,
            'data' => $responseData['data'] ?? null,
        ];

        if (isset($responseData['errors'])) {
            $response['errors'] = $responseData['errors'];
        }

        if (isset($responseData['status'])) {
            $response['status'] = $responseData['status'];
        }

        if (isset($responseData['exception']) && $responseData['exception'] instanceof Throwable && $statusCode === Response::HTTP_OK) {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        if ($response['success'] === false) {
            $response['error_code'] = $responseData['error_code'] ?? 1;
        }

        return [
            'content' => $response,
            'statusCode' => $statusCode,
            'headers' => $headers,
        ];
    }

    /**
     * Format an exception into a standardized array structure.
     * This method takes a Throwable object and extracts relevant information
     * to create a structured array representation of the exception.
     *
     * @param Throwable $throwable The exception to be formatted.
     * @return array An associative array containing:
     *               - 'message': The exception message
     *               - 'file': The file where the exception occurred
     *               - 'line': The line number where the exception occurred
     *               - 'code': The exception code
     *               - 'trace': The exception stack trace
     */
    public function responseException(Throwable $throwable): array
    {
        return [
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'code' => $throwable->getCode(),
            'trace' => $throwable->getTrace(),
        ];
    }

    /**
     * Creates a Symfony Response object based on the formatted response data and desired format.
     * This function takes the formatted response data and creates a Symfony Response
     * object in either XML or JSON format, depending on the specified format.
     *
     * @param array $formattedResponse An array containing the formatted response data with keys:
     *                                 'content' - The response content
     *                                 'statusCode' - The HTTP status code
     *                                 'headers' - Any additional headers
     * @param string $format The desired response format ('xml' or 'json')
     * @throws Exception
     */
    public function createResponse(array $formattedResponse, string $format): LaravelApiResponse
    {
        /** @var array $content */
        $content = $formattedResponse['content'];
        /** @var int $statusCode */
        $statusCode = $formattedResponse['statusCode'];
        /** @var array $headers */
        $headers = $formattedResponse['headers'];

        switch ($format) {
            case 'xml':
                $xml = $this->arrayToXml($content);
                return response($xml, $statusCode, array_merge($headers, [
                    'Content-Type' => 'application/xml',
                ]));
            case 'json':
            default:
                return response()->json($content, $statusCode, $headers);
        }
    }

    /**
     * Generates and formats an API response.
     * This method processes the given data, formats it according to the specified parameters,
     * logs the response, applies content negotiation, and optionally compresses the response.
     *
     * @param array|null $data The data to be included in the response. If null, an empty array will be used.
     * @param int $statusCode The HTTP status code for the response.
     * @param array $headers Additional headers to be included in the response. Default is an empty array.
     * @param string|null $apiVersion The API version to be included in the response, if configured. Default is null.
     * @return LaravelApiResponse A Symfony Response object containing the formatted and processed response.
     * @throws JsonException If there's an error in JSON encoding/decoding.
     * @throws Exception If there's a general error in processing the response.
     */
    public function response(
        ?array $data,
        int $statusCode,
        array $headers = [],
        ?string $apiVersion = null
    ): LaravelApiResponse {
        $responseData = $data ?? [];
        $responseData = $this->addApiVersion($responseData, $apiVersion);

        $formattedResponse = $this->format($responseData, $statusCode, $headers);

        (new Logging())->logResponse(
            request()->method(),
            request()->url(),
            $statusCode,
            fluent($formattedResponse)->scope('content')->toArray(),
        );

        /** @var string $acceptHeader */
        $acceptHeader = request()->header('Accept');
        $responseFormat = (new ContentNegotiation())->type($acceptHeader);

        $response = $this->createResponse($formattedResponse, $responseFormat);

        if (Config::boolean('laravel-api-response.enable_compression', true)) {
            $content = $response->getContent();
            if ($content !== false) {
                $compressedContent = gzencode($content);
                if ($compressedContent !== false) {
                    $response->setContent($compressedContent);
                    $response->headers->set('Content-Encoding', 'gzip');
                }
            }
        }

        return $response;
    }

    /**
     * Filter the data array to include only specified fields.
     *
     * @param array $data The original data array
     * @param array $fields The fields to include in the response
     * @return array The filtered data array
     */
    public function fields(array $data, array $fields): array
    {
        if ($fields === []) {
            return $data;
        }

        $filteredData = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $filteredData[$field] = $data[$field];
            }
        }

        return $filteredData;
    }

    /**
     * Generate an ETag for the given data.
     *
     * @throws JsonException
     */
    public function generateETag(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Check if the resource has not been modified.
     *
     * @throws DateMalformedStringException
     */
    public function getNotModified(string $etag, DateTime $lastModified): bool
    {
        $ifNoneMatch = request()->header('If-None-Match');
        $ifModifiedSince = request()->header('If-Modified-Since');

        if ($ifNoneMatch && $ifNoneMatch === $etag) {
            return true;
        }

        if ($ifModifiedSince) {
            $ifModifiedSince = new DateTime($ifModifiedSince);
            if ($lastModified <= $ifModifiedSince) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the last modified date from the data.
     * This method assumes that the data array has a 'updated_at' key.
     * Modify this method if your data structure is different.
     *
     * @throws DateMalformedStringException
     */
    public function getLastModifiedDate(array $data): DateTime
    {
        /** @var DateTime|string $lastModified */
        $lastModified = $data['updated_at'] ?? new DateTime();
        return is_string($lastModified) ? new DateTime($lastModified) : $lastModified;
    }

    /**
     * Transform data using Laravel's API Resources.
     *
     * @param mixed $data The data to be transformed.
     * @param string|null $resourceClass The fully qualified class name of the API Resource to use.
     * @return array|JsonResource The transformed data.
     */
    protected function transformData(mixed $data, ?string $resourceClass): array|JsonResource
    {
        if ($resourceClass && class_exists($resourceClass)) {
            if ($data instanceof LengthAwarePaginator || (is_array($data) || $data instanceof Collection)) {
                /** @var JsonResource|Collection $collection */
                $collection = $resourceClass::collection($data);

                if ($collection instanceof JsonResource) {
                    return $collection;
                }

                if ($collection instanceof Collection) {
                    return $collection->toArray();
                }

                // If it's not a JsonResource or Collection, which is unlikely but possible
                return is_array($collection) ? $collection : [
                    'data' => $collection,
                ];
            }

            /** @var JsonResource $resource */
            $resource = new $resourceClass($data);

            if ($resource instanceof JsonResource) {
                return $resource;
            }

            // If it's not a JsonResource, which is unlikely but possible
            return is_array($resource) ? $resource : [
                'data' => $resource,
            ];
        }

        if ($data instanceof JsonResource) {
            return $data;
        }

        return is_array($data) ? $data : [
            'data' => $data,
        ];
    }

    /**
     * Converts an array to XML format.
     * This function recursively converts an associative array to XML format.
     * It can handle nested arrays and creates a hierarchical XML structure.
     *
     * @param array $array The array to be converted to XML.
     * @param string|null $rootElement The name of the root element. If null, 'root' will be used.
     * @param SimpleXMLElement|null $xml The SimpleXMLElement object to append to. If null, a new one will be created.
     * @return string The XML representation of the input array as a string.
     * @throws Exception If there's an error in XML conversion.
     */
    private function arrayToXml(array $array, ?string $rootElement = null, ?SimpleXMLElement $xml = null): string
    {
        if (! $xml instanceof SimpleXMLElement) {
            $xml = new SimpleXMLElement($rootElement ?? '<root/>');
        }

        /** @var array|string $value */
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->arrayToXml($value, $key, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        $result = $xml->asXML();
        if ($result === false) {
            throw new RuntimeException('Failed to convert array to XML');
        }

        return $result;
    }

    /**
     * Adds the API version to the response data if configured to do so.
     * This function checks if an API version should be included in the response
     * based on the configuration. If so, it adds the version to the 'content'
     * key of the data array.
     *
     * @param array $data The original response data array.
     * @param string|null $apiVersion The API version to be added, or null if not applicable.
     * @return array The modified data array, potentially including the API version.
     */
    private function addApiVersion(array $data, ?string $apiVersion): array
    {
        if ($apiVersion !== null && Config::boolean('api-response.response_structure.include_api_version', false)) {
            if (! isset($data['content']) || ! is_array($data['content'])) {
                $data['content'] = [];
            }
            $data['content']['api_version'] = $apiVersion;
        }
        return $data;
    }
}
