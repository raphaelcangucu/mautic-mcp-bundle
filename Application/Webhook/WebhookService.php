<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Webhook;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\WebhookBundle\Entity\Event;
use Mautic\WebhookBundle\Entity\Webhook;
use Mautic\WebhookBundle\Model\WebhookModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebhookService
{
    public function __construct(
        private WebhookModel $model,
        private Connection $connection,
        private CorePermissions $permissions,
        private HttpClientInterface $httpClient
    ) {}

    public function read(string $action, ?int $id, ?int $afterId, int $page, int $limit): array
    {
        $this->assertAny(['webhook:webhooks:viewown', 'webhook:webhooks:viewother']);
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        return match ($action) {
            'list'        => $this->list($page, $limit),
            'get'         => $this->get($id),
            'events'      => ['events' => $this->model->getEvents()],
            'incremental' => $this->incremental($id, $afterId ?? 0, $limit),
            default       => throw new BadRequestHttpException('Action must be list, get, events, or incremental.'),
        };
    }

    public function write(string $action, ?int $id, array $data, bool $confirm, ?string $expectedDateModified): array
    {
        return match ($action) {
            'create' => $this->save(null, $data, null),
            'update' => $this->save($id, $data, $expectedDateModified),
            'delete' => $this->delete($id, $confirm, $expectedDateModified),
            'test'   => $this->test($id, $confirm),
            default  => throw new BadRequestHttpException('Action must be create, update, delete, or test.'),
        };
    }

    private function list(int $page, int $limit): array
    {
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.MAUTIC_TABLE_PREFIX.'webhooks');
        $items = $this->connection->fetchAllAssociative('SELECT w.id, w.name, w.description, w.webhook_url AS webhookUrl, w.is_published AS isPublished, w.date_added AS dateAdded, w.date_modified AS dateModified, w.marked_unhealthy_at AS markedUnhealthyAt, GROUP_CONCAT(e.event_type ORDER BY e.event_type) AS triggers FROM '.MAUTIC_TABLE_PREFIX.'webhooks w LEFT JOIN '.MAUTIC_TABLE_PREFIX.'webhook_events e ON e.webhook_id=w.id GROUP BY w.id ORDER BY w.id DESC LIMIT :limit OFFSET :offset', ['limit' => $limit, 'offset' => ($page - 1) * $limit], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER]);
        foreach ($items as &$item) {
            $item['triggers'] = null === $item['triggers'] ? [] : explode(',', (string) $item['triggers']);
        }
        $hasMore = $page * $limit < $total;

        return ['page' => $page, 'limit' => $limit, 'count' => count($items), 'total' => $total, 'hasMore' => $hasMore, 'nextPage' => $hasMore ? $page + 1 : null, 'items' => $items];
    }

    private function get(?int $id): array
    {
        $webhook = $this->entity($id);

        return ['webhook' => $this->normalize($webhook)];
    }

    private function incremental(?int $webhookId, int $afterId, int $limit): array
    {
        $params = ['afterId' => $afterId, 'limit' => $limit];
        $where = 'l.id > :afterId';
        if (null !== $webhookId) {
            $where .= ' AND l.webhook_id = :webhookId';
            $params['webhookId'] = $webhookId;
        }
        $items = $this->connection->fetchAllAssociative('SELECT l.id, l.webhook_id AS webhookId, w.name AS webhookName, l.status_code AS statusCode, l.date_added AS dateAdded, l.note, l.runtime FROM '.MAUTIC_TABLE_PREFIX.'webhook_logs l INNER JOIN '.MAUTIC_TABLE_PREFIX.'webhooks w ON w.id=l.webhook_id WHERE '.$where.' ORDER BY l.id ASC LIMIT :limit', $params, ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]);
        $nextCursor = [] === $items ? $afterId : (int) end($items)['id'];

        return ['afterId' => $afterId, 'nextCursor' => $nextCursor, 'count' => count($items), 'hasMore' => count($items) === $limit, 'items' => $items];
    }

    private function save(?int $id, array $data, ?string $expectedDateModified): array
    {
        $this->assertAny(null === $id ? ['webhook:webhooks:create'] : ['webhook:webhooks:editown', 'webhook:webhooks:editother']);
        $webhook = null === $id ? $this->model->getEntity() : $this->entity($id);
        if (!$webhook instanceof Webhook) {
            throw new NotFoundHttpException('Webhook could not be initialized.');
        }
        $this->assertVersion($webhook, $expectedDateModified);
        foreach (['name' => 'setName', 'description' => 'setDescription', 'webhookUrl' => 'setWebhookUrl', 'secret' => 'setSecret', 'eventsOrderbyDir' => 'setEventsOrderbyDir', 'isPublished' => 'setIsPublished'] as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $webhook->{$setter}($data[$key]);
            }
        }
        if (array_key_exists('triggers', $data)) {
            $events = new ArrayCollection();
            foreach (array_unique((array) $data['triggers']) as $trigger) {
                $event = (new Event())->setEventType((string) $trigger)->setWebhook($webhook);
                $events->add($event);
            }
            $webhook->setEvents($events);
        }
        $this->model->saveEntity($webhook);

        return ['status' => null === $id ? 'created' : 'updated', 'webhook' => $this->normalize($webhook)];
    }

    private function delete(?int $id, bool $confirm, ?string $expectedDateModified): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Deleting a webhook requires confirm=true.');
        }
        $this->assertAny(['webhook:webhooks:deleteown', 'webhook:webhooks:deleteother']);
        $webhook = $this->entity($id);
        $this->assertVersion($webhook, $expectedDateModified);
        $deletedId = $webhook->getId();
        $this->model->deleteEntity($webhook);

        return ['status' => 'deleted', 'successIds' => [$deletedId], 'failureIds' => []];
    }

    private function test(?int $id, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Testing a webhook performs an external request and requires confirm=true.');
        }
        $this->assertAny(['webhook:webhooks:editown', 'webhook:webhooks:editother']);
        $webhook = $this->entity($id);
        $started = microtime(true);
        $payload = ['event' => 'mautic.mcp.webhook.test', 'timestamp' => gmdate(DATE_ATOM), 'webhookId' => $webhook->getId()];
        $headers = ['Content-Type' => 'application/json'];
        if (null !== $webhook->getSecret() && '' !== $webhook->getSecret()) {
            $headers['Webhook-Signature'] = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $webhook->getSecret());
        }
        try {
            $response = $this->httpClient->request('POST', (string) $webhook->getWebhookUrl(), ['headers' => $headers, 'json' => $payload, 'timeout' => 10]);
            $statusCode = $response->getStatusCode();

            return ['status' => $statusCode >= 200 && $statusCode < 300 ? 'delivered' : 'rejected', 'statusCode' => $statusCode, 'runtimeMs' => (int) ((microtime(true) - $started) * 1000)];
        } catch (\Throwable $exception) {
            return ['status' => 'failed', 'statusCode' => null, 'runtimeMs' => (int) ((microtime(true) - $started) * 1000), 'error' => $exception->getMessage()];
        }
    }

    private function entity(?int $id): Webhook
    {
        $webhook = $this->model->getEntity($id);
        if (!$webhook instanceof Webhook || null === $id) {
            throw new NotFoundHttpException('Webhook was not found.');
        }

        return $webhook;
    }

    private function normalize(Webhook $webhook): array
    {
        return ['id' => $webhook->getId(), 'name' => $webhook->getName(), 'description' => $webhook->getDescription(), 'webhookUrl' => $webhook->getWebhookUrl(), 'triggers' => array_values(array_map(static fn (Event $event): string => (string) $event->getEventType(), $webhook->getEvents()->toArray())), 'isPublished' => $webhook->isPublished(), 'dateModified' => $webhook->getDateModified()?->format(DATE_ATOM)];
    }

    private function assertVersion(Webhook $webhook, ?string $expected): void
    {
        if (null !== $expected && $webhook->getDateModified()?->getTimestamp() !== (new \DateTimeImmutable($expected))->getTimestamp()) {
            throw new ConflictHttpException('Webhook changed since expectedDateModified. Read it again before writing.');
        }
    }

    private function assertAny(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->permissions->checkPermissionExists($permission) && $this->permissions->isGranted($permission)) {
                return;
            }
        }
        throw new AccessDeniedException('Permission denied.');
    }
}
