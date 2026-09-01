<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class MauticMcpExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('mcp')) {
            return;
        }

        $container->prependExtensionConfig('mcp', [
            'app'               => 'mautic',
            'version'           => '0.7.0',
            'description'       => 'Full Mautic automation MCP server',
            'instructions'      => 'Operate Mautic automation, analytics, CRM, forms, and webhooks. Prefer read tools and previews; write, send, merge, delete, and external webhook operations require approval.',
            'discovery'         => [
                'scan_dirs'    => [
                    'plugins/MauticMcpBundle',
                ],
                'exclude_dirs' => [
                    'plugins/MauticMcpBundle/Tests',
                ],
            ],
            'client_transports' => [
                'stdio' => true,
                'http'  => true,
            ],
            'http'              => [
                'path'    => '/mcp',
                'session' => [
                    'store'      => 'cache',
                    'cache_pool' => 'cache.mcp.sessions',
                    'prefix'     => 'mautic-mcp-',
                    'ttl'        => 3600,
                ],
            ],
        ]);
    }

    /**
     * @param mixed[] $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Config'));
        $loader->load('services.php');
    }
}
