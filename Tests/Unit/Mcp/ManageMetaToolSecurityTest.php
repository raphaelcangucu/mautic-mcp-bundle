<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Mcp;

use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\ManageMetaTool;
use PHPUnit\Framework\TestCase;

final class ManageMetaToolSecurityTest extends TestCase
{
    public function testDryRunPayloadRedactsAllConnectionCredentials(): void
    {
        $tool = (new \ReflectionClass(ManageMetaTool::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(ManageMetaTool::class, 'redactCredentials');
        $payload = $method->invoke($tool, ['data' => [
            'name' => 'Primary',
            'app_secret' => 'secret-value',
            'access_token' => 'access-value',
            'verify_token' => 'verify-value',
        ]]);

        self::assertSame('Primary', $payload['data']['name']);
        self::assertSame('[REDACTED]', $payload['data']['app_secret']);
        self::assertSame('[REDACTED]', $payload['data']['access_token']);
        self::assertSame('[REDACTED]', $payload['data']['verify_token']);
        self::assertStringNotContainsString('secret-value', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
