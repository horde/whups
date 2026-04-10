<?php

declare(strict_types=1);

/**
 * Bootstrap file for PHPUnit tests
 */

$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../../../running/horde/vendor/autoload.php',
    __DIR__ . '/../../bundle/vendor/autoload.php',
];

$autoloader = null;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        $autoloader = require_once $path;
        break;
    }
}

if (!$autoloader) {
    fwrite(STDERR, "Could not find Composer autoloader. Run 'composer install' first.\n");
    fwrite(STDERR, "Tried paths:\n");
    foreach ($autoloadPaths as $path) {
        fwrite(STDERR, "  - $path\n");
    }
    exit(1);
}

// Register Whups PSR-4 namespace for development (src/ and test/)
$whupsRoot = dirname(__DIR__);
$whupsSrc = $whupsRoot . '/src';
$whupsTest = __DIR__;
if (is_dir($whupsSrc) && method_exists($autoloader, 'addPsr4')) {
    $autoloader->addPsr4('Horde\\Whups\\', $whupsSrc);
    $autoloader->addPsr4('Horde\\Whups\\Test\\', $whupsTest);
}

// Register Whups classmap (lib/) for legacy classes like Whups_Driver, Whups_Ticket
$whupsLib = $whupsRoot . '/lib';
if (is_dir($whupsLib) && method_exists($autoloader, 'addClassMap')) {
    $classmap = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($whupsLib)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace($whupsLib . '/', '', $file->getPathname());
        // Horde classmap convention: lib/Driver.php → Whups_Driver
        // Special case: lib/Whups.php → Whups (not Whups_Whups)
        $baseName = str_replace(['/', '.php'], ['_', ''], $relative);
        $class = ($baseName === 'Whups') ? 'Whups' : 'Whups_' . $baseName;
        $classmap[$class] = $file->getPathname();
    }
    $autoloader->addClassMap($classmap);
}

date_default_timezone_set('UTC');
