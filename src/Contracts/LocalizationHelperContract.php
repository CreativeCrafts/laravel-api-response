<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Contracts;

interface LocalizationHelperContract
{
    public function localize(string $message): string;
}
