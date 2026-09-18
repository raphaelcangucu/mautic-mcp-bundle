<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Application\System;

use Doctrine\DBAL\Connection;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticMcpBundle\Application\System\McpTokenService;
use PHPUnit\Framework\TestCase;

if (!defined('MAUTIC_TABLE_PREFIX')) {
    define('MAUTIC_TABLE_PREFIX', '');
}

final class McpTokenServiceTest extends TestCase
{
    public function testIssueAddsATokenWithoutRevokingTheOnesInUse(): void
    {
        $connection = $this->connection();
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::once())
            ->method('insert')
            ->with('oauth2_accesstokens', self::callback(static function (array $row): bool {
                self::assertSame(9, $row['client_id']);
                self::assertSame(7, $row['user_id']);
                self::assertNull($row['scope']);
                self::assertGreaterThan(time(), $row['expires_at']);

                return true;
            }));

        $issued = (new McpTokenService($connection))->issue($this->user());

        self::assertSame(123, $issued['id']);
        self::assertNotSame('', $issued['token']);
        self::assertSame(substr($issued['token'], 0, 8).'...', $issued['preview']);
    }

    public function testIssueHonoursACustomTtl(): void
    {
        $connection = $this->connection();
        $connection->expects(self::once())
            ->method('insert')
            ->with('oauth2_accesstokens', self::callback(static function (array $row): bool {
                self::assertEqualsWithDelta(time() + 60, $row['expires_at'], 5);

                return true;
            }));

        (new McpTokenService($connection))->issue($this->user(), 60);
    }

    public function testRotateStillReplacesEveryToken(): void
    {
        $connection = $this->connection();
        $connection->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $work) => $work($connection));
        $connection->expects(self::once())
            ->method('delete')
            ->with('oauth2_accesstokens', ['client_id' => 9, 'user_id' => 7]);
        $connection->expects(self::once())->method('insert');

        (new McpTokenService($connection))->rotate($this->user());
    }

    public function testRevokeByIdIsScopedToTheOwnerAndTheMcpClient(): void
    {
        $connection = $this->connection();
        $connection->expects(self::once())
            ->method('delete')
            ->with('oauth2_accesstokens', ['id' => 421, 'client_id' => 9, 'user_id' => 7])
            ->willReturn(1);

        self::assertTrue((new McpTokenService($connection))->revokeById($this->user(), 421));
    }

    public function testRevokeByIdReportsWhenNothingMatched(): void
    {
        $connection = $this->connection();
        $connection->method('delete')->willReturn(0);

        self::assertFalse((new McpTokenService($connection))->revokeById($this->user(), 999));
    }

    public function testListActiveTruncatesEachTokenForDisplay(): void
    {
        $connection = $this->connection();
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => '421', 'token' => 'abcdefghijklmnop', 'expires_at' => '1800000000'],
        ]);

        $tokens = (new McpTokenService($connection))->listActive($this->user());

        self::assertCount(1, $tokens);
        self::assertSame(421, $tokens[0]['id']);
        self::assertSame('abcdefgh...', $tokens[0]['preview']);
        self::assertSame('abcdefghijklmnop', $tokens[0]['token']);
    }

    private function connection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(9);
        $connection->method('lastInsertId')->willReturn('123');

        return $connection;
    }

    private function user(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(7);

        return $user;
    }
}
