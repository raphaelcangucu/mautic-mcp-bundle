<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Management;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class MutationExecutor
{
    public function __construct(
        private CacheInterface $cache
    ) {}

    public function execute(string $scope, ?string $idempotencyKey, array $payload, callable $operation): array
    {
        $key = trim((string) $idempotencyKey);
        if ('' === $key) {
            return $operation();
        }

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $item = $this->cache->get('mautic_mcp_idempotency_'.hash('sha256', $scope.':'.$key), function (ItemInterface $item) use ($hash, $operation): array {
            $item->expiresAfter(86400);

            return ['hash' => $hash, 'result' => $operation()];
        });

        if ($item['hash'] !== $hash || !is_array($item['result'])) {
            throw new ConflictHttpException('The idempotencyKey was already used with a different payload.');
        }

        return $item['result'] + ['idempotencyKey' => $key, 'replayed' => true];
    }

    public function dryRun(string $resource, string $action, array $payload): array
    {
        return [
            'status'     => 'dry_run',
            'dryRun'     => true,
            'wouldWrite' => true,
            'resource'   => $resource,
            'action'     => $action,
            'payload'    => $payload,
        ];
    }
}
