<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Event;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class IncrementalEventService
{
    public function __construct(
        private Connection $connection,
        private CorePermissions $permissions
    ) {}

    public function read(string $stream, int $afterId, int $limit, ?int $contactId, ?int $campaignId): array
    {
        $this->assertRead();
        $limit = max(1, min(500, $limit));
        [$table, $dateColumn, $select] = match ($stream) {
            'audit'             => ['audit_log', 'date_added', 'id, user_id AS userId, user_name AS userName, bundle, object, object_id AS objectId, action, details, date_added AS dateAdded'],
            'contact_activity'  => ['lead_event_log', 'date_added', 'id, lead_id AS contactId, user_id AS userId, user_name AS userName, bundle, object, object_id AS objectId, action, properties, date_added AS dateAdded'],
            'campaign_activity' => ['campaign_lead_event_log', 'date_triggered', 'id, event_id AS eventId, lead_id AS contactId, campaign_id AS campaignId, date_triggered AS dateTriggered, is_scheduled AS isScheduled, system_triggered AS systemTriggered, metadata, channel, channel_id AS channelId'],
            default             => throw new BadRequestHttpException('Stream must be audit, contact_activity, or campaign_activity.'),
        };
        $where = ['id > :afterId'];
        $params = ['afterId' => max(0, $afterId), 'limit' => $limit];
        if (null !== $contactId && 'audit' !== $stream) {
            $where[] = 'lead_id = :contactId';
            $params['contactId'] = $contactId;
        }
        if (null !== $campaignId && 'campaign_activity' === $stream) {
            $where[] = 'campaign_id = :campaignId';
            $params['campaignId'] = $campaignId;
        }
        $items = $this->connection->fetchAllAssociative('SELECT '.$select.' FROM '.MAUTIC_TABLE_PREFIX.$table.' WHERE '.implode(' AND ', $where).' ORDER BY id ASC LIMIT :limit', $params, ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
        foreach ($items as &$item) {
            foreach (['details', 'properties', 'metadata'] as $field) {
                if (isset($item[$field]) && is_string($item[$field])) {
                    $decoded = json_decode($item[$field], true);
                    if (JSON_ERROR_NONE === json_last_error()) {
                        $item[$field] = $decoded;
                    }
                }
            }
        }
        $nextCursor = [] === $items ? max(0, $afterId) : (int) end($items)['id'];

        return ['stream' => $stream, 'afterId' => max(0, $afterId), 'nextCursor' => $nextCursor, 'count' => count($items), 'hasMore' => count($items) === $limit, 'orderedBy' => $dateColumn, 'items' => $items];
    }

    private function assertRead(): void
    {
        foreach (['lead:leads:viewown', 'lead:leads:viewother'] as $permission) {
            if ($this->permissions->isGranted($permission)) {
                return;
            }
        }
        throw new AccessDeniedException('Permission denied.');
    }
}
