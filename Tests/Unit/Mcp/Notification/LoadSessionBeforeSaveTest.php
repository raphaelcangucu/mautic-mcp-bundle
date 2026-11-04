<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Mcp\Notification;

use Mcp\Server;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Transport\InMemoryTransport;
use MauticPlugin\MauticMcpBundle\Mcp\Notification\LoadSessionBeforeSave;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoadSessionBeforeSaveTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: array<string, mixed>}> */
    public static function unhandledNotifications(): iterable
    {
        yield 'cancelled' => ['notifications/cancelled', ['requestId' => 2, 'reason' => 'timeout']];
        yield 'roots list changed' => ['notifications/roots/list_changed', []];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('unhandledNotifications')]
    public function testANotificationNobodyHandlesDoesNotBreakTheSessionSave(string $method, array $params): void
    {
        $store = new InMemorySessionStore(60);
        $server = Server::builder()
            ->setServerInfo('test', '1')
            ->setSession($store)
            ->addNotificationHandler(new LoadSessionBeforeSave())
            ->build();

        $server->run(new InMemoryTransport([
            $this->encode(['id' => 1, 'method' => 'initialize', 'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities'    => new \stdClass(),
                'clientInfo'      => ['name' => 'test', 'version' => '1'],
            ]]),
            $this->encode(['method' => 'notifications/initialized']),
            $this->encode(['method' => $method] + ([] === $params ? [] : ['params' => $params])),
        ]));

        $this->addToAssertionCount(1);
    }

    /** @param array<string, mixed> $message */
    private function encode(array $message): string
    {
        return json_encode(['jsonrpc' => '2.0'] + $message, \JSON_THROW_ON_ERROR);
    }
}
