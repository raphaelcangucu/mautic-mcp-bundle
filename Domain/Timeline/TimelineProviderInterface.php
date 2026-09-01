<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Domain\Timeline;

use Mautic\LeadBundle\Entity\Lead;

interface TimelineProviderInterface
{
    /**
     * @param array<string, mixed> $filters
     * @param array<int, string>   $orderBy
     *
     * @return array<string, mixed>
     */
    public function getTimeline(Lead $contact, array $filters, array $orderBy, int $page, int $limit): array;
}
