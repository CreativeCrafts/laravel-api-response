<?php

namespace CreativeCrafts\LaravelApiResponse;

use CreativeCrafts\LaravelApiResponse\Commands\LaravelApiResponseCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelApiResponseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-api-response');
    }
}
