<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Domain\Timeline;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;

final class EventLogTimelineProvider implements TimelineProviderInterface
{
    public function __construct(
        private LeadModel $leadModel,
    ) {
    }

    public function getTimeline(Lead $contact, array $filters, array $orderBy, int $page, int $limit): array
    {
        // TODO: Keep this provider swappable. Other Mautic branches may prefer direct
        // repository access or a different timeline aggregation path.
        [$payload] = $this->leadModel->getEngagements($contact, $filters, $orderBy, $page, $limit, false);

        return $payload;
    }
}
