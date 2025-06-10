<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse;

use CreativeCrafts\LaravelApiResponse\Exceptions\Handler;
use CreativeCrafts\LaravelApiResponse\Helpers\HateoasLinkGenerator;
use CreativeCrafts\LaravelApiResponse\Helpers\LocalizationHelper;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseFormatter;
use CreativeCrafts\LaravelApiResponse\Helpers\ResponseStructureValidator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelApiResponseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name(name: 'laravel-api-response')
            ->hasConfigFile(configFileName: 'api-response')
            ->publishesServiceProvider(providerName: 'LaravelApiResponseServiceProvider')
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub(vendorSlashRepoName: 'creativecrafts/laravel-api-response');
            });
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(abstract: ResponseFormatter::class);
        $this->app->singleton(abstract: LocalizationHelper::class);
        $this->app->singleton(abstract: HateoasLinkGenerator::class);
        $this->app->singleton(abstract: ResponseStructureValidator::class);

        // Register config with a specific tag
        $this->publishes([
            __DIR__ . '/../config/api-response.php' => config_path(path: 'api-response.php'),
        ], 'api-response-config');
    }

    public function packageBooted(): void
    {
        $this->app->singleton(LaravelApi::class, function (Application $app): LaravelApi {
            return new LaravelApi(
                $app->make(abstract: ResponseFormatter::class),
                $app->make(abstract: LocalizationHelper::class),
                $app->make(abstract: HateoasLinkGenerator::class),
                $app->make(abstract: ResponseStructureValidator::class),
            );
        });

        $this->app->alias(abstract: LaravelApi::class, alias: 'laravel-api');

        $this->app->afterResolving('laravel-api', function (LaravelApi $api): void {
            $api->applyCompression($this->app->make(Router::class));
        });

        // Register the custom exception handler if enabled in config
        if (Config::boolean(key: 'api-response.use_exception_handler', default: true)) {
            $this->app->extend(ExceptionHandlerContract::class, function ($handler, Container $app): Handler {
                /** @var LaravelApi $api */
                $api = $app->make(abstract: LaravelApi::class);
                return new Handler($api, $app);
            });
        }

        $this->configureLogging();
    }

    /**
     * Configure logging for the API responses.
     */
    private function configureLogging(): void
    {
        $logChannel = Config::string(key: 'api-response.log_channel', default: 'api');

        Config::set('logging.channels.api', [
            'driver' => 'daily',
            'path' => storage_path(path: 'logs/api.log'),
            'level' => 'debug',
            'days' => 14,
        ]);

        Log::channel($logChannel);
    }
}
