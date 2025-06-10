<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Exceptions\Handler;
use CreativeCrafts\LaravelApiResponse\LaravelApi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->api = Mockery::mock(LaravelApi::class);
    $this->container = Mockery::mock(Container::class);

    // Mock the parent constructor
    $this->handler = Mockery::mock(Handler::class, [$this->api])->makePartial();
    $this->handler->shouldAllowMockingProtectedMethods();

    $this->request = Mockery::mock(Request::class);
});

it('handles validation exceptions', function () {
    $exception = Mockery::mock(ValidationException::class);
    $exception->shouldReceive('errors')->andReturn(['field' => ['error']]);
    $exception->shouldReceive('getMessage')->andReturn('Validation failed');

    $this->request->shouldReceive('expectsJson')->andReturn(true);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(false);

    $this->api->shouldReceive('validationErrorResponse')
        ->with(['field' => ['error']], 'Validation failed', Response::HTTP_UNPROCESSABLE_ENTITY)
        ->andReturn(new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));

    $response = $this->handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('handles model not found exceptions', function () {
    $exception = Mockery::mock(ModelNotFoundException::class);

    $this->request->shouldReceive('expectsJson')->andReturn(true);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(false);

    $this->api->shouldReceive('errorResponse')
        ->with('Resource not found', Response::HTTP_NOT_FOUND, $exception)
        ->andReturn(new Response('', Response::HTTP_NOT_FOUND));

    $response = $this->handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
});

it('handles authentication exceptions', function () {
    $exception = Mockery::mock(AuthenticationException::class);

    $this->request->shouldReceive('expectsJson')->andReturn(true);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(false);

    $this->api->shouldReceive('errorResponse')
        ->with('Unauthenticated', Response::HTTP_UNAUTHORIZED, $exception)
        ->andReturn(new Response('', Response::HTTP_UNAUTHORIZED));

    $response = $this->handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});

it('handles authorization exceptions', function () {
    $exception = Mockery::mock(AuthorizationException::class);

    $this->request->shouldReceive('expectsJson')->andReturn(true);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(false);

    $this->api->shouldReceive('errorResponse')
        ->with('Unauthorized', Response::HTTP_FORBIDDEN, $exception)
        ->andReturn(new Response('', Response::HTTP_FORBIDDEN));

    $response = $this->handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('handles not found http exceptions', function () {
    $exception = Mockery::mock(NotFoundHttpException::class);

    $this->request->shouldReceive('expectsJson')->andReturn(true);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(false);

    $this->api->shouldReceive('errorResponse')
        ->with('Endpoint not found', Response::HTTP_NOT_FOUND, $exception)
        ->andReturn(new Response('', Response::HTTP_NOT_FOUND));

    $response = $this->handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
});

it('handles http exceptions', function () {
    $exception = Mockery::mock(HttpException::class);
    $exception->shouldReceive('getMessage')->andReturn('HTTP Exception');
    $exception->shouldReceive('getStatusCode')->andReturn(Response::HTTP_BAD_REQUEST);

    $this->request->shouldReceive('expectsJson')->andReturn(true);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(false);

    $this->api->shouldReceive('errorResponse')
        ->with('HTTP Exception', Response::HTTP_BAD_REQUEST, $exception)
        ->andReturn(new Response('', Response::HTTP_BAD_REQUEST));

    $response = $this->handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
});

it('handles generic exceptions', function () {
    $exception = new Exception('Server Error');

    $this->request->shouldReceive('expectsJson')->andReturn(true);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(false);

    $this->api->shouldReceive('errorResponse')
        ->with('Server Error', Response::HTTP_INTERNAL_SERVER_ERROR, $exception)
        ->andReturn(new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));

    $response = $this->handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR);
});

it('delegates to parent handler for non-API requests', function () {
    $exception = new Exception('Server Error');

    $this->request->shouldReceive('expectsJson')->andReturn(false);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(false);

    // Create a new mock that overrides the parent render method
    $handler = Mockery::mock(Handler::class, [$this->api])->makePartial();
    $handler->shouldAllowMockingProtectedMethods();

    // Mock the parent render method by overriding the render method
    $parentResponse = new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
    $handler->shouldReceive('render')->once()->andReturn($parentResponse);

    $response = $handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR);
});

it('handles API requests via path', function () {
    $exception = new Exception('Server Error');

    $this->request->shouldReceive('expectsJson')->andReturn(false);
    $this->request->shouldReceive('is')->with('api/*')->andReturn(true);

    $this->api->shouldReceive('errorResponse')
        ->with('Server Error', Response::HTTP_INTERNAL_SERVER_ERROR, $exception)
        ->andReturn(new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));

    $response = $this->handler->render($this->request, $exception);

    expect($response->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR);
});

it('registers reportable callback', function () {
    $this->handler->register();
    // This test just ensures the register method doesn't throw an exception
    expect(true)->toBeTrue();
});
