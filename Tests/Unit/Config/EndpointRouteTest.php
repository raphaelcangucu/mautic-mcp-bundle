<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class EndpointRouteTest extends TestCase
{
    public function testEndpointSitsBehindTheCoreApiFirewall(): void
    {
        $routes = (require dirname(__DIR__, 3).'/Config/config.php')['routes'];

        self::assertSame('/mcp', $routes['api']['mautic_mcp_http_endpoint']['path']);
        self::assertArrayNotHasKey('public', $routes);
    }
}
