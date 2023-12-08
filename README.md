# A simple package to have a consistent api response.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-api-response.svg?style=flat-square)](https://packagist.org/packages/creativecraft/laravel-api-response)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-api-response/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecraft/laravel-api-response/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-api-response/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecraft/laravel-api-response/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecraft/laravel-api-response.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-api-response)

A simple handy package to have a consistent api response.

## Installation

You can install the package via composer:

```bash
composer require creativecrafts/laravel-api-response
```

## Usage

```php
// respond with success
$message = 'Success message';
$return LaravelApi::successResponse($message);

// respond with success and data
$message = 'Success message';
$data = ['name' => 'test'];
$return LaravelApi::successResponse($message, $data);

// respond with created
$data = [
    'id' => 1,
    'name' => 'Test',
]
$response = LaravelApi::createdResponse($data);

// respond with exception. Exception is optional and will only be used in local or development environment
$exception = new Exception('Test exception');
$message = 'Internal server error';
$errorCodes = 5001;
$statusCode = 500;
return LaravelApi::errorResponse($message, $statusCode, $exception, $errorCodes);

// respond with error
$message = 'Missing required parameters';
$statusCode = 406;
return LaravelApi::errorResponse($message, $statusCode);

// app/Exceptions/Handler.php can be modified to return the response
public function render($request, Throwable $e): Response|JsonResponse|ResponseAlias
    {
        if ($request->expectsJson()) {
            if ($e instanceof PostTooLargeException) {
                return LaravelApi::errorResponse("Size of attached file should be less " . ini_get("upload_max_filesize") . "B", ResponseAlias::HTTP_REQUEST_ENTITY_TOO_LARGE, $e);
            }

            if ($e instanceof ValidationException) {
                return LaravelApi::errorResponse($e->validator->errors()->first(), ResponseAlias::HTTP_UNPROCESSABLE_ENTITY, $e);
            }

            if ($e instanceof ModelNotFoundException) {
                return LaravelApi::errorResponse('Entry for ' . str_replace('App\\', '', $e->getModel()) . ' not found', ResponseAlias::HTTP_NOT_FOUND, $e);
            }

            if ($e instanceof AuthenticationException) {
                return LaravelApi::errorResponse($e->getMessage(), ResponseAlias::HTTP_UNAUTHORIZED, $e);
            }

            if ($e instanceof AuthorizationException) {
                return LaravelApi::errorResponse($e->getMessage(), ResponseAlias::HTTP_FORBIDDEN, $e);
            }

            if ($e instanceof ThrottleRequestsException) {
                return LaravelApi::errorResponse($e->getMessage(), ResponseAlias::HTTP_TOO_MANY_REQUESTS, $e);
            }

            if ($e instanceof Exception) {
                return LaravelApi::errorResponse($e->getMessage(), ResponseAlias::HTTP_INTERNAL_SERVER_ERROR, $e);
            }

            if ($e instanceof Error) {
                return LaravelApi::errorResponse($e->getMessage(), ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        return parent::render($request, $e);

```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Godspower Oduose](https://github.com/rockblings)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
