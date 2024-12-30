<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Contracts;

interface HateoasLinkGeneratorContract
{
    public function generateLinks(array $links): array;

    public function generate(string $route, array $params = [], string $rel = 'self'): array;
}
