<?php

declare(strict_types=1);

/**
 * Injectable URL generator wrapping RoutesProvider.
 *
 * Provides named-route URL generation for Whups controllers, replacing
 * direct Whups::urlFor() static calls.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Service;

use Horde\Core\Uri\RoutesProvider;

class UrlGenerator
{
    public function __construct(
        private readonly RoutesProvider $provider,
        private readonly string $webroot,
        private readonly array $environ = [],
    ) {}

    /**
     * Generate a relative URL for a named route.
     *
     * Extra params not consumed by the route path become query string
     * parameters.
     */
    public function urlFor(string $routeName, array $params = []): string
    {
        return $this->provider->generateNamedPath($routeName, $params) ?? '';
    }

    /**
     * Generate a fully qualified (absolute) URL for a named route.
     */
    public function absoluteUrlFor(string $routeName, array $params = []): string
    {
        $path = $this->provider->generateNamedPath($routeName, $params);
        if ($path === null) {
            return '';
        }

        $host = $this->environ['HTTP_HOST']
            ?? $this->environ['SERVER_NAME']
            ?? 'localhost';

        $scheme = (!empty($this->environ['HTTPS']) && $this->environ['HTTPS'] !== 'off')
            ? 'https'
            : 'http';

        return $scheme . '://' . $host . $path;
    }

    /**
     * Return the application webroot (e.g. "/whups").
     */
    public function getWebroot(): string
    {
        return $this->webroot;
    }

    /**
     * Build URL for a named default view (mybugs, search, etc.).
     */
    public function defaultViewUrl(string $viewName = 'mybugs'): string
    {
        return $this->webroot . '/' . $viewName;
    }
}
