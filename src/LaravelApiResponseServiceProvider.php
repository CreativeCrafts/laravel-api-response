<?php

namespace CreativeCrafts\LaravelApiResponse;

use CreativeCrafts\LaravelApiResponse\Commands\LaravelApiResponseCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelApiResponseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-api-response')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-api-response_table')
            ->hasCommand(LaravelApiResponseCommand::class);
    }
}
