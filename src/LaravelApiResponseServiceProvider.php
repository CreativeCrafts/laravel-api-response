<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse;

use CreativeCrafts\LaravelApiResponse\Helpers\HateoasLinkGenerator;
use CreativeCrafts\LaravelApiResponse\Helpers\LocalizationHelper;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseFormatter;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseStructureValidator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelApiResponseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-api-response')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        $this->app->singleton(LaravelApi::class, function (Application $app): LaravelApi {
            return new LaravelApi(
                $app->make(ResponseFormatter::class),
                $app->make(LocalizationHelper::class),
                $app->make(HateoasLinkGenerator::class),
                $app->make(ResponseStructureValidator::class),
            );
        });

        $this->app->alias(LaravelApi::class, 'laravel-api');

        $this->app->afterResolving('laravel-api', function (LaravelApi $api): void {
            $api->applyCompression($this->app->make(Router::class));
        });
        $this->configureLogging();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ResponseFormatter::class);
        $this->app->singleton(LocalizationHelper::class);
        $this->app->singleton(HateoasLinkGenerator::class);
        $this->app->singleton(ResponseStructureValidator::class);
    }

    /**
     * Configure logging for the API responses.
     */
    private function configureLogging(): void
    {
        $logChannel = Config::string('api-response.log_channel', 'api');

        Config::set('logging.channels.api', [
            'driver' => 'daily',
            'path' => storage_path('logs/api.log'),
            'level' => 'debug',
            'days' => 14,
        ]);

        Log::channel($logChannel);
    }
}
