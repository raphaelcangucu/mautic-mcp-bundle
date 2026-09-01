<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\System;

use Doctrine\DBAL\Connection;
use Mautic\UserBundle\Entity\User;

final class McpTokenService
{
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

    public function rotate(User $user): array
    {
        return $this->connection->transactional(function () use ($user): array {
            $this->revoke($user);
            $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
            $expiresAt = time() + 31536000;
            $this->connection->insert(MAUTIC_TABLE_PREFIX.'oauth2_accesstokens', ['client_id' => $this->clientId(), 'user_id' => $user->getId(), 'token' => $token, 'expires_at' => $expiresAt, 'scope' => null]);

            return ['token' => $token, 'expiresAt' => gmdate(DATE_ATOM, $expiresAt)];
        });
    }

    public function revoke(User $user): void
    {
        $this->connection->delete(MAUTIC_TABLE_PREFIX.'oauth2_accesstokens', ['client_id' => $this->clientId(), 'user_id' => $user->getId()]);
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
