<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Helpers;

use CreativeCrafts\LaravelApiResponse\Contracts\ContentNegotiationContract;

final readonly class ContentNegotiation implements ContentNegotiationContract
{
    /**
     * Negotiate the content type based on the Accept header.
     * This method takes an Accept header string and attempts to determine the
     * appropriate content type based on the supported types. If a supported type
     * is found, it returns the corresponding format; otherwise, it defaults to JSON.
     *
     * @param string $acceptHeader The Accept header string.
     * @return string The content type format to use for the response.
     */
    public function type(string $acceptHeader): string
    {
        $supportedTypes = [
            'application/json' => 'json',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
        ];

        $acceptedTypes = explode(',', $acceptHeader);
        foreach ($acceptedTypes as $type) {
            $type = trim($type);
            if (isset($supportedTypes[$type])) {
                return $supportedTypes[$type];
            }
        }
        // Default to JSON if no supported type is found
        return 'json';
    }
}
