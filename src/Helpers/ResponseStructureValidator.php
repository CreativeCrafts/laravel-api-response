<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Helpers;

use CreativeCrafts\LaravelApiResponse\Contracts\ResponseStructureValidatorContract;
use InvalidArgumentException;

final readonly class ResponseStructureValidator implements ResponseStructureValidatorContract
{
    /**
     * Validates and completes the response structure configuration.
     * This function checks if all required keys are present in the provided structure,
     * throws an exception if any are missing, and merges the provided structure with
     * default values for any missing optional keys.
     *
     * @param array $structure The response structure configuration to validate and complete.
     * @return array The validated and completed response structure configuration.
     * @throws InvalidArgumentException If any required keys are missing from the structure.
     */
    public function validate(array $structure): array
    {
        $requiredKeys = [
            'success_key',
            'message_key',
            'data_key',
            'errors_key',
            'error_code_key',
            'meta_key',
            'links_key',
            'include_api_version',
        ];

        $missingKeys = array_diff($requiredKeys, array_keys($structure));

        if ($missingKeys !== []) {
            throw new InvalidArgumentException(
                message: 'Missing required keys in response structure configuration: ' . implode(', ', $missingKeys)
            );
        }
        return $structure;
    }
}
