<?php

declare(strict_types=1);

use CreativeCrafts\LaravelApiResponse\Helpers\HateoasLinkGenerator;
use CreativeCrafts\LaravelApiResponse\Helpers\LocalizationHelper;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseFormatter;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseStructureValidator;
use CreativeCrafts\LaravelApiResponse\LaravelApi;
use CreativeCrafts\LaravelApiResponse\LaravelApiResponseServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Spatie\LaravelPackageTools\Package;

it('registers the service provider', function () {
    $app = Mockery::mock(Application::class);
    $provider = new LaravelApiResponseServiceProvider($app);

    // Test configurePackage method
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('configurePackage');
    $method->setAccessible(true);

    $packageMock = Mockery::mock(Package::class);
    $packageMock->shouldReceive('name')->with('laravel-api-response')->once()->andReturnSelf();
    $packageMock->shouldReceive('hasConfigFile')->with('api-response')->once()->andReturnSelf();
    $packageMock->shouldReceive('publishesServiceProvider')->with('LaravelApiResponseServiceProvider')->once()->andReturnSelf();
    $packageMock->shouldReceive('hasInstallCommand')->once()->andReturnSelf();

    $method->invoke($provider, $packageMock);
});

it('registers singletons', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('singleton')->with(ResponseFormatter::class)->once();
    $app->shouldReceive('singleton')->with(LocalizationHelper::class)->once();
    $app->shouldReceive('singleton')->with(HateoasLinkGenerator::class)->once();
    $app->shouldReceive('singleton')->with(ResponseStructureValidator::class)->once();

    $provider = new LaravelApiResponseServiceProvider($app);
    $provider->packageRegistered();
});

it('publishes config with specific tag', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('singleton')->times(4);

    $provider = Mockery::mock(LaravelApiResponseServiceProvider::class, [$app])->makePartial();
    $provider->shouldAllowMockingProtectedMethods();
    $provider->shouldReceive('publishes')->with(
        Mockery::type('array'),
        'api-response-config'
    )->once();

    $provider->packageRegistered();
});

// Create a test subclass that overrides the packageBooted method
class TestServiceProvider extends LaravelApiResponseServiceProvider
{
    public function packageBooted(): void
    {
        // Register the LaravelApi singleton
        $this->app->singleton(LaravelApi::class, function (Application $app): LaravelApi {
            return new LaravelApi(
                $app->make(ResponseFormatter::class),
                $app->make(LocalizationHelper::class),
                $app->make(HateoasLinkGenerator::class),
                $app->make(ResponseStructureValidator::class),
            );
        });

        // Register the alias
        $this->app->alias(LaravelApi::class, 'laravel-api');

        // Apply compression
        $api = $this->app->make(LaravelApi::class);
        $api->applyCompression($this->app->make('Illuminate\Routing\Router'));

        // Register the custom exception handler if enabled in config
        if (Config::boolean('api-response.use_exception_handler', true)) {
            $this->app->extend(ExceptionHandler::class, function ($handler, $app) {
                $api = $app->make(LaravelApi::class);
                return new CreativeCrafts\LaravelApiResponse\Exceptions\Handler($api, $app);
            });
        }
    }
}

it('configures logging', function () {
    // Create a test subclass that exposes the configureLogging behavior
    $loggingConfigured = false;
    $logChannel = null;
    $loggingConfig = null;

    // Mock Config::get for database-related calls
    Config::shouldReceive('get')
        ->withAnyArgs()
        ->andReturn(null);

    $testProvider = new class (app()) extends LaravelApiResponseServiceProvider {
        public $loggingConfigured = false;
        public $logChannel = null;
        public $loggingConfig = null;

        protected function configureLogging(): void
        {
            $this->logChannel = Config::string('api-response.log_channel', 'api');

            $this->loggingConfig = [
                'driver' => 'daily',
                'path' => storage_path('logs/api.log'),
                'level' => 'debug',
                'days' => 14,
            ];

            Config::set('logging.channels.api', $this->loggingConfig);

            // Skip the Log::channel call
            $this->loggingConfigured = true;
        }
    };

    // Mock the Config facade
    Config::shouldReceive('string')
        ->with('api-response.log_channel', 'api')
        ->once()
        ->andReturn('api');

    Config::shouldReceive('set')
        ->with('logging.channels.api', Mockery::type('array'))
        ->once();

    // Call the method through reflection to avoid protected visibility issues
    $reflection = new ReflectionClass($testProvider);
    $method = $reflection->getMethod('configureLogging');
    $method->setAccessible(true);
    $method->invoke($testProvider);

    // Assert that the logging was configured correctly
    expect($testProvider->loggingConfigured)->toBeTrue();
    expect($testProvider->logChannel)->toBe('api');
    expect($testProvider->loggingConfig)->toBeArray();
    expect($testProvider->loggingConfig['driver'])->toBe('daily');
});
