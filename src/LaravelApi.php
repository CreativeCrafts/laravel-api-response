<?php

namespace CreativeCrafts\LaravelApiResponse;

use Error;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class LaravelApi
{
    /**
     * @param array|JsonResource|ResourceCollection $data
     * @param int $statusCode
     * @param array $headers
     * @return JsonResponse
     */
    protected function response(array|JsonResource|ResourceCollection $data = [], int $statusCode = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        $response = (new self)->prepareResponse($data, $statusCode, $headers);
        return response()->json($response['content'], $response['statusCode'], $response['headers']);
    }

    /**
     * @param array|JsonResource|ResourceCollection $data
     * @param int $statusCode
     * @param array $headers
     * @return array
     */
    protected function prepareResponse(array|JsonResource|ResourceCollection $data, int $statusCode, array $headers): array
    {
        $response = [
            'success' => $data['success'],
            'message' => $data['message'] ?? null,
            'result' => $data['result'] ?? null,
        ];

        if (isset($data['errors'])) {
            $response['errors'] = $data['errors'];
        }

        if (isset($data['status'])) {
            $response['status'] = $data['status'];
        }

        if (isset($data['exception']) && ($data['exception'] instanceof Error || $data['exception'] instanceof Exception)) {
            if (config('app.env') !== 'production') {
                $response['exception'] = [
                    'message' => $data['exception']->getMessage(),
                    'file' => $data['exception']->getFile(),
                    'line' => $data['exception']->getLine(),
                    'code' => $data['exception']->getCode(),
                    'trace' => $data['exception']->getTrace(),
                ];
            }

            if ($statusCode === 200) {
                $statusCode = 500;
            }
        }

        if ($data['success'] === false) {
            if (isset($data['error_code'])) {
                $response['error_code'] = $data['error_code'];
            } else {
                $response['error_code'] = 1;
            }
        }

        return [
            'content' => $response,
            'statusCode' => $statusCode,
            'headers' => $headers
        ];
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    public static function successResponse(string $message = '', array $result = []): JsonResponse
    {
        $data = [
            'success' => true,
            'message' =>$message,
            'result' => $result,
        ];

        return (new self)->response($data);
    }

    public static function createdResponse(array $result): JsonResponse
    {
        $data = [
            'success' => true,
            'message' => 'Created successfully.',
            'result' => $result,
        ];
        return (new self)->response($data, Response::HTTP_CREATED);
    }

    public static function errorResponse(string $message = 'Bad request.', int $statusCode = Response::HTTP_BAD_REQUEST, Exception $exception = null, int $errorCode = 1): JsonResponse
    {
        $data = [
            'success' => false,
            'message' => $message,
            'exception' => $exception,
            'error_code' => $errorCode
        ];

        return (new self)->response($data, $statusCode);
    }
}