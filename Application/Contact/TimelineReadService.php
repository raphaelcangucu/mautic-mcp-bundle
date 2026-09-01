<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Contact;

use MauticPlugin\MauticMcpBundle\Domain\Timeline\TimelineProviderInterface;
use MauticPlugin\MauticMcpBundle\Presentation\Normalizer\TimelinePayloadNormalizer;

class TimelineReadService
{
    public function __construct(
        private ContactReadService $contactReadService,
        private TimelineProviderInterface $timelineProvider,
        private TimelinePayloadNormalizer $timelinePayloadNormalizer,
    ) {
    }

    public function getTimeline(int $contactId, string $search = '', int $limit = 25, int $page = 1): array
    {
        $contact = $this->contactReadService->getViewableContactOrFail($contactId);

        $payload = $this->timelineProvider->getTimeline(
            $contact,
            [
                'search'        => trim($search),
                'includeEvents' => [],
                'excludeEvents' => [],
            ],
            ['timestamp', 'DESC'],
            max(1, $page),
            max(1, min($limit, 100))
        );

        return $this->timelinePayloadNormalizer->normalize($contact->getId(), $payload, $page, $limit);
    }
}
