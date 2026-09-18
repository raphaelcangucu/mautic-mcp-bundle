<?php

declare(strict_types=1);

/*
 * Lets the suite run from a bare checkout of this plugin, outside a Mautic
 * installation. Inside Mautic the application bootstrap already provides all
 * of this.
 */

$vendor = dirname(__DIR__).'/vendor';

if (!is_file($vendor.'/autoload.php')) {
    fwrite(STDERR, "Run \"composer install\" before the test suite.\n");
    exit(1);
}

require $vendor.'/autoload.php';

if (!defined('MAUTIC_TABLE_PREFIX')) {
    define('MAUTIC_TABLE_PREFIX', '');
}

// The plugin declares no autoload section, because Mautic discovers its
// classes by convention. Map the namespace onto the checkout so the suite can
// find both the code under test and the tests themselves.
spl_autoload_register(static function (string $class): void {
    $prefix = 'MauticPlugin\\MauticMcpBundle\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__).'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($path)) {
        require $path;
    }
});

// Mautic's ParameterLoader globs <mautic>/plugins/*/Config for plugin
// parameters. Composer installs core-lib without that sibling directory, so
// Finder throws DirectoryNotFoundException the first time any Mautic entity is
// constructed. An empty placeholder is enough to make the glob resolve.
$placeholder = $vendor.'/mautic/plugins/PlaceholderBundle/Config';

if (!is_dir($placeholder)) {
    @mkdir($placeholder, 0777, true);
}
