<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelApiResponse\Helpers;

use CreativeCrafts\LaravelApiResponse\Contracts\HateoasLinkGeneratorContract;
use Exception;
use Illuminate\Support\Facades\Route;

final readonly class HateoasLinkGenerator implements HateoasLinkGeneratorContract
{
    /**
     * Generate HATEOAS links based on the provided link information.
     * This method processes an array of link definitions and generates
     * corresponding HATEOAS links using the link generator.
     *
     * @param array<string, string|array{route: string, params?: array}> $links An associative array where keys are link relations
     *                     and values are either string routes or arrays containing
     *                     'route' and optional 'params' keys.
     * @return array<string, array> An associative array of generated HATEOAS links, where keys
     *               are the link relations and values are the generated link objects.
     */
    public function generateLinks(array $links): array
    {
        $generatedLinks = [];
        foreach ($links as $rel => $routeInfo) {
            if (is_string($routeInfo)) {
                $generatedLinks[$rel] = $this->generate($routeInfo, [], $rel);
            } elseif (is_array($routeInfo) && isset($routeInfo['route']) && is_string($routeInfo['route'])) {
                $params = isset($routeInfo['params']) && is_array($routeInfo['params']) ? $routeInfo['params'] : [];
                $generatedLinks[$rel] = $this->generate($routeInfo['route'], $params, $rel);
            }
        }
        return $generatedLinks;
    }

    /**
     * Generate a HATEOAS link for a given route.
     * This function creates a HATEOAS (Hypermedia as the Engine of Application State) link
     * for a specified route, including the URL, relationship, and HTTP method.
     *
     * @param string $route The name of the route for which to generate the link.
     * @param array $params An optional array of parameters to be passed to the route. Default is an empty array.
     * @param string $rel The relationship of the link to the current resource. Default is 'self'.
     * @return array An associative array containing the HATEOAS link information:
     *               - 'href': The URL of the link.
     *               - 'rel': The relationship of the link.
     *               - 'method': The HTTP method associated with the route.
     */
    public function generate(string $route, array $params = [], string $rel = 'self'): array
    {
        return [
            'href' => route($route, $params),
            'rel' => $rel,
            'method' => $this->getRouteMethod($route),
        ];
    }

    /**
     * Get the HTTP method associated with a given route.
     * This function retrieves the HTTP method (e.g., GET, POST, PUT, DELETE) for a specified route.
     * If the route is found, it returns the first method associated with the route.
     * If the route is not found or has no methods, it defaults to 'GET'.
     *
     * @param string $route The name of the route for which to retrieve the HTTP method.
     * @return string The HTTP method associated with the route, or 'GET' if not found.
     */
    private function getRouteMethod(string $route): string
    {
        try {
            $routeInfo = Route::getRoutes()->getByName($route);
            if ($routeInfo === null) {
                return 'GET';
            }

            $methods = $routeInfo->methods();
            return empty($methods) ? 'GET' : fluent($methods)->string('0')->toString();
        } catch (Exception $e) {
            return 'GET';
        }
    }
}
