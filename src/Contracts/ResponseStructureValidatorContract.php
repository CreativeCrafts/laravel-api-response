<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Contracts;

interface ResponseStructureValidatorContract
{
    public function validate(array $structure): array;
}
