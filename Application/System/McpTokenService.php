<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\System;

use Doctrine\DBAL\Connection;
use Mautic\UserBundle\Entity\User;

final class McpTokenService
{
    public const DEFAULT_TTL = 31536000;

    public function __construct(
        private Connection $connection
    ) {}

    public function current(User $user): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT token,expires_at FROM '.MAUTIC_TABLE_PREFIX.'oauth2_accesstokens WHERE client_id=:clientId AND user_id=:userId AND expires_at>UNIX_TIMESTAMP() ORDER BY id DESC LIMIT 1', ['clientId' => $this->clientId(), 'userId' => $user->getId()]);
        if (false === $row) {
            return null;
        }

        return ['token' => (string) $row['token'], 'expiresAt' => gmdate(DATE_ATOM, (int) $row['expires_at'])];
    }

    /**
     * Every unexpired token the user owns, newest first.
     *
     * @return list<array{id:int,token:string,preview:string,expiresAt:string}>
     */
    public function listActive(User $user): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id,token,expires_at FROM '.MAUTIC_TABLE_PREFIX.'oauth2_accesstokens WHERE client_id=:clientId AND user_id=:userId AND expires_at>UNIX_TIMESTAMP() ORDER BY id DESC', ['clientId' => $this->clientId(), 'userId' => $user->getId()]);

        return array_map(static fn (array $row): array => [
            'id'        => (int) $row['id'],
            'token'     => (string) $row['token'],
            'preview'   => substr((string) $row['token'], 0, 8).'...',
            'expiresAt' => gmdate(DATE_ATOM, (int) $row['expires_at']),
        ], $rows);
    }

    /**
     * Adds a token and leaves every existing one working, so a new client can be
     * onboarded without breaking the ones already connected.
     *
     * @return array{id:int,token:string,preview:string,expiresAt:string}
     */
    public function issue(User $user, ?int $ttl = null): array
    {
        $token     = $this->generate();
        $expiresAt = time() + ($ttl ?? self::DEFAULT_TTL);

        $this->connection->insert(MAUTIC_TABLE_PREFIX.'oauth2_accesstokens', ['client_id' => $this->clientId(), 'user_id' => $user->getId(), 'token' => $token, 'expires_at' => $expiresAt, 'scope' => null]);

        return ['id' => (int) $this->connection->lastInsertId(), 'token' => $token, 'preview' => substr($token, 0, 8).'...', 'expiresAt' => gmdate(DATE_ATOM, $expiresAt)];
    }

    /**
     * Replaces every token the user owns with a single fresh one.
     */
    public function rotate(User $user): array
    {
        return $this->connection->transactional(function () use ($user): array {
            $this->revoke($user);

            return $this->issue($user);
        });
    }

    public function revoke(User $user): void
    {
        $this->connection->delete(MAUTIC_TABLE_PREFIX.'oauth2_accesstokens', ['client_id' => $this->clientId(), 'user_id' => $user->getId()]);
    }

    /**
     * Revokes one token. Scoped by owner and client so it can never touch another
     * user's token, nor a non-MCP OAuth token that happens to share the id space.
     */
    public function revokeById(User $user, int $id): bool
    {
        return $this->connection->delete(MAUTIC_TABLE_PREFIX.'oauth2_accesstokens', ['id' => $id, 'client_id' => $this->clientId(), 'user_id' => $user->getId()]) > 0;
    }

    private function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private function clientId(): int
    {
        $id = (int) $this->connection->fetchOne('SELECT id FROM '.MAUTIC_TABLE_PREFIX.'oauth2_clients WHERE name=:name LIMIT 1', ['name' => 'Mautic MCP - Codex']);
        if ($id < 1) {
            throw new \RuntimeException('MCP OAuth client is not configured.');
        }

        return $id;
    }
}
