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

// respond with exception
$exception = new Exception('Test exception');
$message = 'Internal server error';
$errorCodes = 5001;
$statusCode = 500;
return LaravelApi::errorResponse($message, $statusCode, $exception, $errorCodes);

// respond with error
$message = 'Missing required parameters';
$statusCode = 406;
return LaravelApi::errorResponse($message, $statusCode);
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
