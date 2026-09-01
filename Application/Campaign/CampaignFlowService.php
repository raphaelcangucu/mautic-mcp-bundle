<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Campaign;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\AbstractEventAccessor;
use Mautic\CampaignBundle\EventCollector\EventCollector;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CampaignBundle\Model\EventModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\Entity\Email;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CampaignFlowService
{
    public function __construct(
        private CampaignModel $campaignModel,
        private EventModel $eventModel,
        private EventCollector $eventCollector,
        private CorePermissions $permissions,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function listTypes(?string $eventType = null): array
    {
        $catalog = $this->eventCollector->getEvents();
        $groups = [
            'action' => $catalog->getActions(),
            'decision' => $catalog->getDecisions(),
            'condition' => $catalog->getConditions(),
        ];
        if (null !== $eventType && '' !== $eventType) {
            if (!isset($groups[$eventType])) {
                throw new BadRequestHttpException('eventType must be action, decision, or condition.');
            }
            $groups = [$eventType => $groups[$eventType]];
        }

        $result = [];
        foreach ($groups as $group => $definitions) {
            foreach ($definitions as $key => $definition) {
                $result[$group][] = $this->normalizeDefinition((string) $key, $group, $definition);
            }
        }

        return ['eventTypes' => $result];
    }

    public function getFlow(int $campaignId): array
    {
        $campaign = $this->campaign($campaignId, 'view');
        $positions = [];
        foreach ($campaign->getCanvasSettings()['nodes'] ?? [] as $node) {
            $positions[(int) ($node['id'] ?? 0)] = [
                'x' => (int) ($node['positionX'] ?? 0),
                'y' => (int) ($node['positionY'] ?? 0),
            ];
        }
        $events = [];
        foreach ($campaign->getEvents() as $event) {
            if ($event->isDeleted()) {
                continue;
            }
            $events[] = $this->normalizeEvent($event, $positions[(int) $event->getId()] ?? null);
        }

        return [
            'campaignId' => $campaign->getId(),
            'campaignName' => $campaign->getName(),
            'allowRestart' => $campaign->getAllowRestart(),
            'eventCount' => count($events),
            'events' => $events,
        ];
    }

    public function validateFlow(array $events): array
    {
        $normalized = $this->normalizeInput($events);

        return ['valid' => true, 'eventCount' => count($normalized), 'events' => $normalized];
    }

    public function replaceFlow(int $campaignId, array $events, bool $confirm, ?string $expectedDateModified = null): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Replacing a campaign flow requires confirm=true.');
        }
        $campaign = $this->campaign($campaignId, 'edit');
        if (null !== $expectedDateModified && $campaign->getDateModified()?->getTimestamp() !== (new \DateTimeImmutable($expectedDateModified))->getTimestamp()) {
            throw new ConflictHttpException('Campaign changed since expectedDateModified. Read it again before replacing the flow.');
        }
        $normalized = $this->normalizeInput($events, true);

        $this->entityManager->wrapInTransaction(function () use ($campaign, $normalized): void {
            $existing = $campaign->getEvents()->toArray();
            $existingIds = array_values(array_map(static fn (Event $event): string => (string) $event->getId(), $existing));
            if ([] !== $existingIds) {
                $this->eventModel->deleteEventsByEventIds($existingIds);
                foreach ($existing as $event) {
                    $campaign->removeEvent($event);
                }
            }
            [$sessionEvents, $canvas] = $this->buildMauticPayload($normalized);

            $newEvents = $this->campaignModel->setEvents($campaign, $sessionEvents, $canvas, []);
            $violations = $this->validator->validate($campaign);
            foreach ($newEvents as $event) {
                $violations->addAll($this->validator->validate($event));
            }
            if ($violations->count() > 0) {
                $messages = [];
                foreach ($violations as $violation) {
                    $messages[] = $violation->getPropertyPath().': '.$violation->getMessage();
                }
                throw new BadRequestHttpException('Campaign flow validation failed: '.implode('; ', $messages));
            }

            $this->campaignModel->setCanvasSettings($campaign, $canvas, true, $newEvents);
        });

        $this->entityManager->clear();

        return ['status' => 'flow_replaced'] + $this->getFlow($campaignId);
    }

    public function deleteEvents(int $campaignId, array $eventIds, bool $cascade, bool $confirm, ?string $expectedDateModified = null): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Deleting campaign events requires confirm=true.');
        }
        $campaign = $this->campaign($campaignId, 'edit');
        if (null !== $expectedDateModified && $campaign->getDateModified()?->getTimestamp() !== (new \DateTimeImmutable($expectedDateModified))->getTimestamp()) {
            throw new ConflictHttpException('Campaign changed since expectedDateModified. Read it again before deleting events.');
        }
        $requested = array_values(array_unique(array_map('intval', $eventIds)));
        if ([] === $requested) {
            throw new BadRequestHttpException('eventIds must contain at least one event ID.');
        }
        $events = [];
        foreach ($campaign->getEvents() as $event) {
            if ($event->isDeleted()) {
                continue;
            }
            $events[(int) $event->getId()] = $event;
        }
        foreach ($requested as $eventId) {
            if (!isset($events[$eventId])) {
                throw new NotFoundHttpException(sprintf('Event %d does not belong to campaign %d.', $eventId, $campaignId));
            }
        }
        $deleting = array_fill_keys($requested, true);
        do {
            $changed = false;
            foreach ($events as $eventId => $event) {
                $parentId = $event->getParent()?->getId();
                if (null !== $parentId && isset($deleting[$parentId]) && !isset($deleting[$eventId])) {
                    if (!$cascade) {
                        throw new ConflictHttpException(sprintf('Event %d has child event %d. Use cascade=true or delete/reconnect the child first.', $parentId, $eventId));
                    }
                    $deleting[$eventId] = true;
                    $changed = true;
                }
            }
        } while ($changed);
        $deletedIds = array_map('intval', array_keys($deleting));

        $this->entityManager->wrapInTransaction(function () use ($campaign, $events, $deleting, $deletedIds): void {
            $this->eventModel->deleteEventsByEventIds(array_map('strval', $deletedIds));
            foreach ($deleting as $eventId => $_) {
                $campaign->removeEvent($events[$eventId]);
            }
            $canvas = $campaign->getCanvasSettings();
            $canvas['nodes'] = array_values(array_filter($canvas['nodes'] ?? [], static fn (array $node): bool => !isset($deleting[(int) ($node['id'] ?? 0)])));
            $canvas['connections'] = array_values(array_filter($canvas['connections'] ?? [], static fn (array $connection): bool => !isset($deleting[(int) ($connection['sourceId'] ?? 0)]) && !isset($deleting[(int) ($connection['targetId'] ?? 0)])));
            $campaign->setCanvasSettings($canvas);
            $this->campaignModel->saveEntity($campaign);
        });
        $this->entityManager->clear();

        return ['status' => 'events_deleted', 'successIds' => $deletedIds, 'failureIds' => [], 'deletedCount' => count($deletedIds)] + $this->getFlow($campaignId);
    }

    private function normalizeInput(array $events, bool $allowEmpty = false): array
    {
        if ([] === $events && !$allowEmpty) {
            throw new BadRequestHttpException('A campaign flow must contain at least one event.');
        }
        if (count($events) > 200) {
            throw new BadRequestHttpException('A campaign flow cannot contain more than 200 events.');
        }

        $byKey = [];
        foreach ($events as $index => $input) {
            if (!is_array($input)) {
                throw new BadRequestHttpException(sprintf('Event at index %d must be an object.', $index));
            }
            $key = trim((string) ($input['key'] ?? ''));
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $key)) {
                throw new BadRequestHttpException(sprintf('Event at index %d has an invalid key.', $index));
            }
            if (isset($byKey[$key])) {
                throw new BadRequestHttpException(sprintf('Duplicate event key: %s.', $key));
            }
            $eventType = (string) ($input['eventType'] ?? '');
            $type = (string) ($input['type'] ?? '');
            if (!in_array($eventType, ['action', 'decision', 'condition'], true)) {
                throw new BadRequestHttpException(sprintf('Event %s has an invalid eventType.', $key));
            }
            try {
                $definition = $this->eventCollector->getEvents()->getEvent($eventType, $type);
            } catch (\InvalidArgumentException $exception) {
                throw new BadRequestHttpException(sprintf('Event %s uses unavailable type %s (%s).', $key, $type, $eventType), $exception);
            }

            $trigger = is_array($input['trigger'] ?? null) ? $input['trigger'] : [];
            $mode = (string) ($trigger['mode'] ?? 'immediate');
            if (!in_array($mode, ['immediate', 'interval', 'date', 'optimized'], true)) {
                throw new BadRequestHttpException(sprintf('Event %s has an invalid trigger mode.', $key));
            }
            $interval = max(1, (int) ($trigger['amount'] ?? 1));
            $unit = (string) ($trigger['unit'] ?? 'd');
            if ('interval' === $mode && !in_array($unit, ['i', 'h', 'd', 'm', 'y'], true)) {
                throw new BadRequestHttpException(sprintf('Event %s has an invalid interval unit.', $key));
            }
            $date = $trigger['date'] ?? null;
            if ('date' === $mode && (null === $date || '' === $date)) {
                throw new BadRequestHttpException(sprintf('Event %s requires trigger.date.', $key));
            }
            if (null !== $date && '' !== $date) {
                try {
                    $date = (new \DateTimeImmutable((string) $date))->format('Y-m-d H:i:s');
                } catch (\Throwable $exception) {
                    throw new BadRequestHttpException(sprintf('Event %s has an invalid trigger date.', $key), $exception);
                }
            }

            $properties = is_array($input['properties'] ?? null) ? $input['properties'] : [];
            $this->validateKnownProperties($key, $type, $properties);
            $byKey[$key] = [
                'key' => $key,
                'name' => trim((string) ($input['name'] ?? $definition->getLabel() ?: $type)),
                'description' => (string) ($input['description'] ?? ''),
                'type' => $type,
                'eventType' => $eventType,
                'parent' => null === ($input['parent'] ?? null) ? null : (string) $input['parent'],
                'path' => null === ($input['path'] ?? null) ? null : (string) $input['path'],
                'properties' => $properties,
                'trigger' => ['mode' => $mode, 'amount' => $interval, 'unit' => $unit, 'date' => $date],
                'position' => [
                    'x' => (int) ($input['position']['x'] ?? 400 + (($index % 4) * 280)),
                    'y' => (int) ($input['position']['y'] ?? 160 + (intdiv($index, 4) * 180)),
                ],
            ];
        }

        foreach ($byKey as $key => $event) {
            $parent = $event['parent'];
            if (null === $parent) {
                if (null !== $event['path']) {
                    throw new BadRequestHttpException(sprintf('Root event %s cannot define a path.', $key));
                }
                continue;
            }
            if (!isset($byKey[$parent])) {
                throw new BadRequestHttpException(sprintf('Event %s references missing parent %s.', $key, $parent));
            }
            if ($parent === $key) {
                throw new BadRequestHttpException(sprintf('Event %s cannot be its own parent.', $key));
            }
            $parentType = $byKey[$parent]['eventType'];
            if (in_array($parentType, ['decision', 'condition'], true)) {
                if (!in_array($event['path'], ['yes', 'no'], true)) {
                    throw new BadRequestHttpException(sprintf('Event %s must use path yes or no after %s.', $key, $parentType));
                }
            } elseif (null !== $event['path']) {
                throw new BadRequestHttpException(sprintf('Event %s cannot define a path after an action.', $key));
            }
            $this->validateConnectionRestriction($key, $event, $byKey[$parent]);
        }
        $this->assertAcyclic($byKey);

        return array_values($byKey);
    }

    private function buildMauticPayload(array $events): array
    {
        $sessionEvents = [];
        $connections = [];
        $nodes = [['id' => 'lists', 'positionX' => '500', 'positionY' => '30']];
        $ids = [];
        foreach ($events as $event) {
            $ids[$event['key']] = 'new_mcp_'.$event['key'];
        }
        foreach ($events as $event) {
            $id = $ids[$event['key']];
            $sessionEvents[$id] = [
                'id' => $id,
                'name' => $event['name'],
                'description' => $event['description'],
                'type' => $event['type'],
                'eventType' => $event['eventType'],
                'properties' => $event['properties'],
                'triggerMode' => $event['trigger']['mode'],
                'triggerInterval' => $event['trigger']['amount'],
                'triggerIntervalUnit' => $event['trigger']['unit'],
                'triggerDate' => $event['trigger']['date'],
            ];
            $nodes[] = ['id' => $id, 'positionX' => (string) $event['position']['x'], 'positionY' => (string) $event['position']['y']];
            if (null === $event['parent']) {
                $connections[] = ['sourceId' => 'lists', 'targetId' => $id, 'anchors' => ['source' => 'leadsource', 'target' => 'top']];
            } else {
                $connections[] = ['sourceId' => $ids[$event['parent']], 'targetId' => $id, 'anchors' => ['source' => $event['path'] ?? 'bottom', 'target' => 'top']];
            }
        }

        return [$sessionEvents, ['nodes' => $nodes, 'connections' => $connections]];
    }

    private function validateKnownProperties(string $key, string $type, array $properties): void
    {
        if ('email.send' === $type) {
            $emailId = (int) ($properties['email'] ?? 0);
            if ($emailId < 1 || !$this->entityManager->getRepository(Email::class)->find($emailId) instanceof Email) {
                throw new BadRequestHttpException(sprintf('Event %s requires a valid properties.email ID.', $key));
            }
        }
        if ('lead.changepoints' === $type && !is_numeric($properties['points'] ?? null)) {
            throw new BadRequestHttpException(sprintf('Event %s requires numeric properties.points.', $key));
        }
        if ('lead.changetags' === $type) {
            $addTags = $properties['add_tags'] ?? [];
            $removeTags = $properties['remove_tags'] ?? [];
            if (!is_array($addTags) || !is_array($removeTags)) {
                throw new BadRequestHttpException(sprintf('Event %s requires properties.add_tags and properties.remove_tags to be arrays.', $key));
            }
            if ([] === $addTags && [] === $removeTags) {
                throw new BadRequestHttpException(sprintf('Event %s must add or remove at least one tag.', $key));
            }
            foreach ([...$addTags, ...$removeTags] as $tag) {
                if ((!is_string($tag) && !is_int($tag)) || '' === trim((string) $tag)) {
                    throw new BadRequestHttpException(sprintf('Event %s contains an invalid tag name or ID.', $key));
                }
            }
        }
    }

    private function validateConnectionRestriction(string $key, array $event, array $parent): void
    {
        $definition = $this->eventCollector->getEvents()->getEvent($event['eventType'], $event['type']);
        $sources = $definition->getConnectionRestrictions()['source'][$parent['eventType']] ?? [];
        if ([] !== $sources && !in_array($parent['type'], $sources, true)) {
            throw new BadRequestHttpException(sprintf('Event %s cannot follow %s according to Mautic connection restrictions.', $key, $parent['type']));
        }
    }

    private function assertAcyclic(array $events): void
    {
        foreach (array_keys($events) as $start) {
            $seen = [];
            $cursor = $start;
            while (null !== $events[$cursor]['parent']) {
                if (isset($seen[$cursor])) {
                    throw new BadRequestHttpException(sprintf('Campaign flow contains a cycle involving %s.', $cursor));
                }
                $seen[$cursor] = true;
                $cursor = $events[$cursor]['parent'];
            }
        }
    }

    private function campaign(int $id, string $action): Campaign
    {
        $campaign = $this->campaignModel->getEntity($id);
        if (!$campaign instanceof Campaign) {
            throw new NotFoundHttpException(sprintf('Campaign %d was not found.', $id));
        }
        if (!$this->permissions->hasEntityAccess('campaign:campaigns:'.$action.'own', 'campaign:campaigns:'.$action.'other', $campaign->getCreatedBy())) {
            throw new AccessDeniedException(sprintf('Permission denied for campaign %s.', $action));
        }

        return $campaign;
    }

    private function normalizeDefinition(string $key, string $eventType, AbstractEventAccessor $definition): array
    {
        return [
            'type' => $key,
            'eventType' => $eventType,
            'label' => $definition->getLabel(),
            'description' => $definition->getDescription(),
            'propertyForm' => $definition->getFormType(),
            'channel' => $definition->getChannel(),
            'channelIdField' => $definition->getChannelIdField(),
            'connectionRestrictions' => $definition->getConnectionRestrictions(),
        ];
    }

    private function normalizeEvent(Event $event, ?array $position): array
    {
        return [
            'id' => $event->getId(),
            'key' => 'event_'.$event->getId(),
            'name' => $event->getName(),
            'description' => $event->getDescription(),
            'type' => $event->getType(),
            'eventType' => $event->getEventType(),
            'parentId' => $event->getParent()?->getId(),
            'path' => $event->getDecisionPath(),
            'properties' => $event->getProperties(),
            'trigger' => [
                'mode' => $event->getTriggerMode(),
                'amount' => $event->getTriggerInterval(),
                'unit' => $event->getTriggerIntervalUnit(),
                'date' => $event->getTriggerDate()?->format(DATE_ATOM),
            ],
            'position' => $position,
        ];
    }
}
