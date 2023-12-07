<?php

namespace CreativeCrafts\LaravelApiResponse;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelApiResponseServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-api-response');
    }
}
