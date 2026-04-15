<?php

if (!class_exists('Composer\Autoload\ClassLoader', false)) {
    if (is_dir(dirname(__FILE__, 3) . '/vendor')) {
        require_once dirname(__FILE__, 3) . '/vendor/autoload.php';
    } elseif (is_dir(dirname(__FILE__, 4) . '/vendor')) {
        require_once dirname(__FILE__, 4) . '/vendor/autoload.php';
    }
}
require_once dirname(__FILE__) . '/config/horde.local.php';

// Lean router: opt-in via routes.local.php existence
if (defined('HORDE_CONFIG_BASE')
    && class_exists(Horde\Core\FrontRouter::class)
    && is_readable(HORDE_CONFIG_BASE . '/routes.local.php')
) {
    if (Horde\Core\RampageBootstrap::runLean(HORDE_CONFIG_BASE)) {
        exit(0);
    }
    // No match — fall through to full bootstrap
}

// Full bootstrap with HordeCoreMiddleware
Horde\Core\RampageBootstrap::run();
