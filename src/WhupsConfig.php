<?php

declare(strict_types=1);
/**
 * Whups configuration class
 *
 * Provides access to the Whups configuration settings.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(WhupsConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Whups;

use Horde\Core\Config\State;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: WhupsConfigFactory::class, method: 'create')]
class WhupsConfig extends State {}
