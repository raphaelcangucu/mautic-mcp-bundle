<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use MauticPlugin\MauticMcpBundle\Security\PermissionChecker;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

use Symfony\Component\HttpKernel\KernelInterface;

return function (ContainerConfigurator $configurator): void {
    $configurator->parameters()
        ->set('mautic_mcp.allow_stdio_admin_fallback', true);

    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->bind('$allowStdioAdminFallback', param('mautic_mcp.allow_stdio_admin_fallback'))
        ->public();

    $excludes = MauticCoreExtension::DEFAULT_EXCLUDES;
    $excludes[] = 'Application/Meta';
    $excludes[] = 'Mcp/Tool/Meta';

    $services->load('MauticPlugin\\MauticMcpBundle\\', '../')
        ->exclude('../{'.implode(',', $excludes).'}');

    if (class_exists('MauticPlugin\\MauticMetaBundle\\Entity\\MetaAsset')) {
        $services->load('MauticPlugin\\MauticMcpBundle\\Application\\Meta\\', '../Application/Meta');
        $services->load('MauticPlugin\\MauticMcpBundle\\Mcp\\Tool\\Meta\\', '../Mcp/Tool/Meta');
    }

    $services->set(PermissionChecker::class);
    $services->alias(KernelInterface::class, 'kernel');
};
