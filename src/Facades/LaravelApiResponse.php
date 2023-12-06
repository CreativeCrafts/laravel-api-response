<?php

namespace CreativeCrafts\LaravelApiResponse\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CreativeCrafts\LaravelApiResponse\LaravelApiResponse
 */
class LaravelApiResponse extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \CreativeCrafts\LaravelApiResponse\LaravelApiResponse::class;
    }
}
