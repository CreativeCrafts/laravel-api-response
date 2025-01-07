<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Helpers;

use CreativeCrafts\LaravelApiResponse\Contracts\ContentNegotiationContract;

final readonly class ContentNegotiation implements ContentNegotiationContract
{
    /**
     * Determine the preferred content type based on the Accept header.
     * This function parses the Accept header to determine the client's preferred
     * content type. It supports JSON (default) and XML formats.
     *
     * @param string $acceptHeader The Accept header string from the HTTP request.
     * @return string Returns 'xml' if 'application/xml' or 'text/xml' are explicitly
     *                specified in the Accept header. Returns 'json' for all other cases.
     */
    public function type(string $acceptHeader): string
    {
        $supportedTypes = [
            'application/xml' => 'xml',
            'text/xml' => 'xml',
        ];

        $acceptedTypes = explode(',', $acceptHeader);

        foreach ($acceptedTypes as $type) {
            $mimeType = strtolower(trim(explode(';', $type)[0]));
            if (isset($supportedTypes[$mimeType])) {
                return $supportedTypes[$mimeType];
            }
        }

        return 'json';
    }
}
