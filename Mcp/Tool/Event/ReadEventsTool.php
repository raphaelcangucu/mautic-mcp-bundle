<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Event;

use MauticPlugin\MauticMcpBundle\Application\Event\IncrementalEventService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_read_events', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadEventsTool extends AbstractMcpTool
{
    public function __construct(
        private IncrementalEventService $service
    ) {}

    /**
     * Incrementally read audit, contact activity, or campaign activity using a stable afterId cursor.
     */
    #[McpTool(name: 'mautic_read_events', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['audit', 'contact_activity', 'campaign_activity'])] string $stream = 'contact_activity', int $afterId = 0, int $limit = 100, ?int $contactId = null, ?int $campaignId = null): array
    {
        $this->bootstrapExecution();

        return $this->service->read($stream, $afterId, $limit, $contactId, $campaignId);
    }
}
