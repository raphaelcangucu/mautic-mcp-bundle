<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Presentation\Normalizer;

final class TimelinePayloadNormalizer
{
    public function __construct(
        private TimelineEventNormalizer $timelineEventNormalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function normalize(int $contactId, array $payload, int $page, int $limit): array
    {
        $events = [];
        foreach ($payload['events'] ?? [] as $event) {
            if (is_array($event)) {
                $events[] = $this->timelineEventNormalizer->normalize($event);
            }
        }

        return [
            'contact_id' => $contactId,
            'page'       => $payload['page'] ?? max(1, $page),
            'limit'      => $payload['limit'] ?? max(1, min($limit, 100)),
            'total'      => $payload['total'] ?? count($events),
            'max_pages'  => $payload['maxPages'] ?? 1,
            'types'      => $this->normalizeTypes($payload['types'] ?? []),
            'events'     => $events,
        ];
    }

    private function normalizeTypes(mixed $types): array
    {
        if (!is_iterable($types)) {
            return [];
        }

        $normalized = [];

        foreach ($types as $key => $value) {
            if (is_string($key) && '' !== trim($key)) {
                $normalized[] = trim($key);
                continue;
            }

            if (is_string($value) && '' !== trim($value)) {
                $normalized[] = trim($value);
                continue;
            }

            if (is_array($value) && isset($value['type']) && is_string($value['type']) && '' !== trim($value['type'])) {
                $normalized[] = trim($value['type']);
                continue;
            }

        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
