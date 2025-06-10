<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Exceptions;

use CreativeCrafts\LaravelApiResponse\LaravelApi;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * The LaravelApi instance.
     */
    protected LaravelApi $api;

    /**
     * Create a new exception handler instance.
     */
    public function __construct(LaravelApi $api, Container $container = null)
    {
        if ($container instanceof Container) {
            parent::__construct($container);
        }
        $this->api = $api;
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     *
     * @throws Throwable
     */
    public function render($request, Throwable $e): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions and return formatted JSON responses.
     *
     * @throws Exception
     */
    private function handleApiException(Request $request, Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            return $this->api->validationErrorResponse(
                errors: $e->errors(),
                message: in_array($e->getMessage(), ['', '0'], true) ? 'Validation failed' : $e->getMessage()
            );
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->api->errorResponse(
                message: 'Resource not found',
                statusCode: Response::HTTP_NOT_FOUND,
                throwable: $e
            );
        }

        if ($e instanceof AuthenticationException) {
            return $this->api->errorResponse(
                message: 'Unauthenticated',
                statusCode: Response::HTTP_UNAUTHORIZED,
                throwable: $e
            );
        }

        if ($e instanceof AuthorizationException) {
            return $this->api->errorResponse(
                message: 'Unauthorized',
                statusCode: Response::HTTP_FORBIDDEN,
                throwable: $e
            );
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->api->errorResponse(
                message: 'Endpoint not found',
                statusCode: Response::HTTP_NOT_FOUND,
                throwable: $e
            );
        }

        if ($e instanceof HttpException) {
            return $this->api->errorResponse(
                message: in_array($e->getMessage(), ['', '0'], true) ? 'HTTP Exception' : $e->getMessage(),
                statusCode: $e->getStatusCode(),
                throwable: $e
            );
        }

        return $this->api->errorResponse(
            message: 'Server Error',
            statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            throwable: $e
        );
    }
}
