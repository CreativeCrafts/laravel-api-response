# Laravel API Response

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-api-response.svg?style=flat-square)](https://packagist.org/packages/creativecraft/laravel-api-response)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-api-response/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecraft/laravel-api-response/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-api-response/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecraft/laravel-api-response/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Code Coverage](https://codecov.io/gh/creativecrafts/laravel-api-response/branch/main/graph/badge.svg)](https://codecov.io/gh/creativecrafts/laravel-api-response)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-api-response.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-api-response)

Laravel API Response is a powerful and flexible package that provides a standardized way to structure API responses in Laravel applications. It offers a range of features to enhance your API development experience, including consistent response formatting, conditional responses, pagination support, rate limiting, and more.

Table of Contents
=================
    1. [Installation](#installation)
    2. [Configuration](#configuration)
    3. [Basic Usage](#basic-usage)
    4. [Feature](#feature)
        - [Success Response](#success-response)
        - [Error Response](#error-response)
        - [Conditional Response](#conditional-response)
        - [Pagination](#pagination)
        - [Rate Limiting](#rate-limiting)
        - [Response Compression](#response-compression)
        - [Localization](#localization)
        - [HATEOAS Links](#hateoas-links)
        - [Logging](#logging)
    5. [Advanced Usage](#advanced-usage)
    6. [Testing](#testing)
    7. [Changelog](#changelog)
    8. [Contributing](#contributing)
    9. [Security Vulnerabilities](#security-vulnerabilities)
    10. [Credits](#credits)
    11. [License](#license)

## 1. Installation
You can install the package via composer:

```bash
composer require creativecrafts/laravel-api-response
```

## 2. Configuration
The package comes with a default configuration file that you can publish to your application using the following command:

```bash 
php artisan vendor:publish --tag=api-response-config
```
This will create a laravel-api-response.php file in your config directory. You can customize various aspects of the package behavior in this file.

## 3. Basic Usage
There are two ways to use the Laravel API Response in your controllers:

### Using Dependency Injection
You can inject the LaravelApi class:

```php
use CreativeCrafts\LaravelApiResponse\LaravelApi;

class UserController extends Controller
{
    protected $api;

    public function __construct(LaravelApi $api)
    {
        $this->api = $api;
    }

    public function index()
    {
        $users = User::all();
        return $this->api->successResponse('Users retrieved successfully', $users);
    }
}
```

### Using the Facade
For a more Laravel-like experience, you can use the LaravelApiResponse facade:

```php
use CreativeCrafts\LaravelApiResponse\Facades\LaravelApiResponse;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return LaravelApiResponse::successResponse('Users retrieved successfully', $users);
    }
}
```

## 4. Feature
The Laravel API Response package provides a range of features to help you build robust and reliable APIs. Here are some of the key features:

### Success Responses
To return a success response:

```php
return $this->api->successResponse('Operation successful', $data);
```

### Error Responses
To return an error response:

```php
return $this->api->errorResponse('An error occurred', $errorCode, $statusCode);
```

### Conditional Responses
For responses that support caching and conditional requests:

```php
return $this->api->conditionalResponse($data, 'Data retrieved successfully');
```
This method automatically handles ETag and Last-Modified headers for efficient caching.

### Pagination
The package supports Laravel's pagination:

```php
$users = User::paginate(15);
return $this->api->paginatedResponse('Users retrieved successfully', $users);
```

### Rate Limiting
Rate limiting is automatically applied to API routes. You can configure the rate limit in the laravel-api-response.php config file:

```php
'rate_limit_max_attempts' => env('API_RATE_LIMIT_MAX_ATTEMPTS', 60),
'rate_limit_decay_minutes' => env('API_RATE_LIMIT_DECAY_MINUTES', 1),
```

### Response Compression
Response compression can be enabled or disabled in the config:

```php 
'enable_compression' => env('API_RESPONSE_COMPRESSION', true),
```

### Localization
The package supports message localization. Use the localize method to translate messages:

```php
$message = $this->api->localize('messages.welcome');
```

### HATEOAS Links
You can include HATEOAS links in your responses:

```php
$links = [
    'self' => ['href' => '/api/users/1'],
    'posts' => ['href' => '/api/users/1/posts'],
];

return $this->api->successResponse('User retrieved', $userData, 200, [], $links);
```

### Logging
API responses are logged to a dedicated channel. You can configure the channel in the config file:

```php  
'log_channel' => 'api',
```

### Exception Handling
The package includes a custom exception handler that automatically formats exceptions into consistent API responses. This is enabled by default and can be configured in the config file:

```php
'use_exception_handler' => env('API_USE_EXCEPTION_HANDLER', true),
```

When enabled, the exception handler will automatically catch exceptions and format them into API responses with appropriate status codes. For example:

- Validation exceptions will be formatted as validation error responses
- Model not found exceptions will be formatted as 404 error responses
- Authentication exceptions will be formatted as 401 error responses
- Authorization exceptions will be formatted as 403 error responses

This ensures consistent error responses across your API without requiring extra code in your controllers.

## 5. Advanced Usage
The Laravel API Response package provides a range of advanced features to help you build robust and reliable APIs. Here are some of the key features:

### Custom Response Structure
You can customize the response structure in the config file:

```php
'response_structure' => [
    'success_key' => 'success',
    'message_key' => 'message',
    'data_key' => 'data',
    'errors_key' => 'errors',
    'error_code_key' => 'error_code',
    'meta_key' => 'meta',
    'links_key' => '_links',
    'include_api_version' => true,
],
```

### Filtering Response Fields
You can filter the fields returned in the response:

```php
$fields = ['id', 'name', 'email'];
return $this->api->successResponse('User data', $userData, 200, [], [], $fields);
```

### Custom Status Codes
You can specify custom HTTP status codes for your responses:

```php
return $this->api->successResponse('Resource created', $newResource, 201);
```

### Extending with Custom Methods
The LaravelApi class uses Laravel's Macroable trait, allowing you to add custom response methods at runtime:

```php
use CreativeCrafts\LaravelApiResponse\LaravelApi;
use Illuminate\Support\Facades\Response;

LaravelApi::macro('teapotResponse', function ($message = "I'm a teapot") {
    return Response::json(['message' => $message], 418);
});

// Then in your controller:
return $this->api->teapotResponse();
// Or using the facade:
return LaravelApiResponse::teapotResponse("Custom teapot message");
```

This makes it easy to extend the package with your own custom response types without modifying the core code.

### Version 2: Breaking Changes
Version 2 of the package introduces one breaking change.
 - createdResponse method has been removed. Use successResponse instead.

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
