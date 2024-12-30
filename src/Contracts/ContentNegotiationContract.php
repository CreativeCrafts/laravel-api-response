<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Contracts;

interface ContentNegotiationContract
{
    public function type(string $acceptHeader): string;
}
