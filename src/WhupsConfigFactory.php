<?php

declare(strict_types=1);
/**
 * Whups configuration class factory
 *
 * Creates instances of the WhupsConfig class.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(WhupsConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Whups;

use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;

class WhupsConfigFactory
{
    public function __construct(private Injector $injector) {}

    public function create(): WhupsConfig
    {
        $state = $this->injector->get(ConfigLoader::class)->load('whups');
        return new WhupsConfig($state->toArray());
    }
}
