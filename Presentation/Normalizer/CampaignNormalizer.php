<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Presentation\Normalizer;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CategoryBundle\Entity\Category;
use Mautic\UserBundle\Entity\User;

final class CampaignNormalizer
{
    public function normalizeSummary(Campaign $campaign): array
    {
        return [
            'id'            => $campaign->getId(),
            'name'          => $campaign->getName(),
            'description'   => $campaign->getDescription(),
            'category'      => $this->normalizeCategory($campaign->getCategory()),
            'publish_up'    => $this->formatDate($campaign->getPublishUp()),
            'publish_down'  => $this->formatDate($campaign->getPublishDown()),
            'date_added'    => $this->formatDate($campaign->getDateAdded()),
            'date_modified' => $this->formatDate($campaign->getDateModified()),
        ];
    }

    public function normalizeDetails(Campaign $campaign, bool $includeEvents = true): array
    {
        $data = [
            'id'                 => $campaign->getId(),
            'name'               => $campaign->getName(),
            'description'        => $campaign->getDescription(),
            'category'           => $this->normalizeCategory($campaign->getCategory()),
            'created_by'         => $this->normalizeUser($campaign->getCreatedBy()),
            'publish_up'         => $this->formatDate($campaign->getPublishUp()),
            'publish_down'       => $this->formatDate($campaign->getPublishDown()),
            'date_added'         => $this->formatDate($campaign->getDateAdded()),
            'date_modified'      => $this->formatDate($campaign->getDateModified()),
            'allow_restart'      => $campaign->getAllowRestart(),
            'republish_behavior' => $campaign->getRepublishBehavior(),
            'segments'           => $this->normalizeSegments($campaign),
            'forms'              => $this->normalizeForms($campaign),
        ];

        if ($includeEvents) {
            $data['events'] = $this->normalizeEvents($campaign);
        }

        return $data;
    }

    private function normalizeEvents(Campaign $campaign): array
    {
        $events = [];

        foreach ($campaign->getEvents() as $event) {
            $events[] = [
                'id'                    => $event->getId(),
                'name'                  => $event->getName(),
                'description'           => $event->getDescription(),
                'type'                  => $event->getType(),
                'event_type'            => $event->getEventType(),
                'channel'               => $event->getChannel(),
                'channel_id'            => $event->getChannelId(),
                'order'                 => $event->getOrder(),
                'decision_path'         => $event->getDecisionPath(),
                'parent_id'             => $event->getParent()?->getId(),
                'child_ids'             => array_map(
                    static fn (Event $child): int => $child->getId(),
                    $event->getChildren()->toArray()
                ),
                'trigger_date'          => $this->formatDate($event->getTriggerDate()),
                'trigger_interval'      => $event->getTriggerInterval(),
                'trigger_interval_unit' => $event->getTriggerIntervalUnit(),
                'trigger_mode'          => $event->getTriggerMode(),
                'properties'            => $event->getProperties(),
            ];
        }

        return $events;
    }

    private function normalizeSegments(Campaign $campaign): array
    {
        $segments = [];

        foreach ($campaign->getLists() as $segment) {
            $segments[] = [
                'id'   => $segment->getId(),
                'name' => $segment->getName(),
            ];
        }

        return $segments;
    }

    private function normalizeForms(Campaign $campaign): array
    {
        $forms = [];

        foreach ($campaign->getForms() as $form) {
            $forms[] = [
                'id'   => $form->getId(),
                'name' => $form->getName(),
            ];
        }

        return $forms;
    }

    private function normalizeCategory(?Category $category): ?array
    {
        if (!$category instanceof Category) {
            return null;
        }

        return [
            'id'    => $category->getId(),
            'title' => $category->getTitle(),
        ];
    }

    private function normalizeUser(mixed $user): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id'   => $user->getId(),
            'name' => $user->getName(),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if (!$value instanceof \DateTimeInterface) {
            return null;
        }

        return $value->format(\DateTimeInterface::ATOM);
    }
}
