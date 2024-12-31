<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Helpers;

use CreativeCrafts\LaravelApiResponse\Contracts\ContentNegotiationContract;

final readonly class ContentNegotiation implements ContentNegotiationContract
{
    /**
     * Determine the preferred content type based on the Accept header.
     * This function parses the Accept header to determine the client's preferred
     * content type. It supports JSON and XML formats, with JSON as the default
     * if no supported type is found or specified.
     *
     * @param string $acceptHeader The Accept header string from the HTTP request.
     *                             It should contain MIME types and their quality values.
     * @return string The selected content type ('json' or 'xml') based on the
     *                highest quality supported MIME type in the Accept header.
     *                Returns 'json' if no supported type is found or specified.
     */
    public function type(string $acceptHeader): string
    {
        $supportedTypes = [
            'application/json' => 'json',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
        ];

        $acceptedTypes = explode(',', $acceptHeader);
        // @pest-mutate-ignore
        $highestQuality = -1;
        $selectedType = 'json';

        foreach ($acceptedTypes as $type) {
            // @pest-mutate-ignore
            $parts = explode(';', trim($type));
            // @pest-mutate-ignore
            $mimeType = strtolower(trim($parts[0]));
            // @pest-mutate-ignore
            $quality = isset($parts[1]) ? $this->parseQuality($parts[1]) : 1.0;

            if (isset($supportedTypes[$mimeType]) && $quality > $highestQuality) {
                $highestQuality = $quality;
                $selectedType = $supportedTypes[$mimeType];
            }
        }

        return $selectedType;
    }

    /**
     * Parse the quality value from a quality string in an Accept header.
     * This function extracts the quality value (q-value) from a quality string
     * typically found in an Accept header. If no quality value is found, it
     * defaults to 1.0.
     *
     * @param string $qualityString The quality string to parse (e.g., "q=0.8").
     * @return float The parsed quality value as a float between 0 and 1,
     *               or 1.0 if no quality value is found.
     */
    private function parseQuality(string $qualityString): float
    {
        if (preg_match('/q=(\d*\.?\d+)/', $qualityString, $matches)) {
            return (float) $matches[1];
        }
        // @pest-mutate-ignore
        return 1.0;
    }
}
