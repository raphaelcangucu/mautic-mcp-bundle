<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Presentation\Normalizer;

final class TimelineEventNormalizer
{
    /**
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    public function normalize(array $event): array
    {
        return [
            'id'          => $event['eventId'] ?? null,
            'event'       => $event['event'] ?? null,
            'type'        => $event['eventType'] ?? null,
            'label'       => $this->normalizeLabel($event['eventLabel'] ?? null),
            'occurred_at' => $this->formatDate($event['timestamp'] ?? null),
            'contact_id'  => $event['contactId'] ?? null,
            'icon'        => $event['icon'] ?? null,
            'details'     => $event['details'] ?? $event['extra'] ?? [],
        ];
    }

    private function normalizeLabel(mixed $label): mixed
    {
        if (is_array($label) && isset($label['label'])) {
            return $label['label'];
        }

        return $label;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_string($value) && '' !== trim($value)) {
            try {
                return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
            } catch (\Exception) {
                return $value;
            }
        }

        return null;
    }
}
