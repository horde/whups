<?php

declare(strict_types=1);

/**
 * Injectable URL generator wrapping Horde\Routes\Utils.
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

use Horde\Routes\Mapper;
use Horde\Routes\Utils;

class UrlGenerator
{
    private Utils $utils;

    public function __construct(
        private readonly Mapper $mapper,
        private readonly string $webroot,
    ) {
        $this->mapper->environ['SCRIPT_NAME'] = rtrim($webroot, '/');
        $this->utils = new Utils($this->mapper);
    }

    /**
     * Generate a relative URL for a named route.
     *
     * Extra params not consumed by the route path become query string
     * parameters.
     */
    public function urlFor(string $routeName, array $params = []): string
    {
        return $this->utils->urlFor($routeName, $params);
    }

    /**
     * Generate a fully qualified (absolute) URL for a named route.
     */
    public function absoluteUrlFor(string $routeName, array $params = []): string
    {
        $params['qualified'] = true;

        return $this->utils->urlFor($routeName, $params);
    }
}
