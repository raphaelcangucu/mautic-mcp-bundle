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
            'version'           => '0.17.0',
            'description'       => 'Full Mautic automation MCP server',
            'instructions'      => 'Operate Mautic automation, analytics, CRM, forms, webhooks, WhatsApp, and Instagram. Prefer read tools and previews; write, send, merge, delete, and external operations require approval.',
            'discovery'         => [
                'scan_dirs'    => array_merge(['plugins/MauticMcpBundle'], array_column($this->providers($container), 'scan_directory')),
                'exclude_dirs' => [
                    'plugins/MauticMcpBundle/Tests',
                    'plugins/MauticMcpBundle/Mcp/Tool/Meta',
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
        foreach ($this->providers($container) as $provider) {
            $prototype = new \Symfony\Component\DependencyInjection\Definition();
            $prototype->setAutowired(true)->setAutoconfigured(true)->setPublic(true);
            $prototype->setBindings(['$allowStdioAdminFallback' => '%mautic_mcp.allow_stdio_admin_fallback%']);
            $loader->registerClasses($prototype, $provider['namespace'], $provider['directory'].'/*');
        }
    }
    /** Discover opt-in providers from registered bundles, never arbitrary directories. */
    private function providers(ContainerBuilder $container): array
    {
        $providers = [];
        foreach ($container->getParameter('kernel.bundles') as $class) {
            $directory = dirname((new \ReflectionClass($class))->getFileName());
            $manifest = $directory.'/Config/mcp.php';
            if (!is_file($manifest)) { continue; }
            $container->addResource(new \Symfony\Component\Config\Resource\FileResource($manifest));
            $provider = require $manifest;
            $real = realpath($provider['directory'] ?? '');
            if (!$real || !str_starts_with($real, realpath($directory).DIRECTORY_SEPARATOR) || empty($provider['namespace'])) {
                throw new \LogicException('Invalid MCP provider manifest: '.$manifest);
            }
            $providers[] = ['namespace' => $provider['namespace'], 'directory' => $real, 'scan_directory' => \Symfony\Component\Filesystem\Path::makeRelative($real, $container->getParameter('kernel.project_dir'))];
        }
        return $providers;
    }

}
