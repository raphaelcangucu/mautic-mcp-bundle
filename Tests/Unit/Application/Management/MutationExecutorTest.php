<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Application\Management;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MutationExecutorTest extends TestCase
{
    public function testMarksOnlyCachedExecutionAsReplay(): void
    {
        $executor = new MutationExecutor(new ArrayAdapter());
        $calls = 0;
        $operation = static function () use (&$calls): array { ++$calls; return ['status' => 'created', 'id' => 42]; };

        $first = $executor->execute('contact', 'stable-key', ['email' => 'one@example.test'], $operation);
        $second = $executor->execute('contact', 'stable-key', ['email' => 'one@example.test'], $operation);

        self::assertFalse($first['replayed']);
        self::assertTrue($second['replayed']);
        self::assertSame(1, $calls);
        self::assertSame(42, $second['id']);
    }

    public function testRejectsReusedKeyWithDifferentPayload(): void
    {
        $executor = new MutationExecutor(new ArrayAdapter());
        $executor->execute('contact', 'stable-key', ['email' => 'one@example.test'], static fn (): array => ['status' => 'created']);

        $this->expectException(ConflictHttpException::class);
        $executor->execute('contact', 'stable-key', ['email' => 'two@example.test'], static fn (): array => ['status' => 'created']);
    }
}
