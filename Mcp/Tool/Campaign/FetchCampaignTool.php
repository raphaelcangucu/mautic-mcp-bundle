<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Campaign;

use MauticPlugin\MauticMcpBundle\Application\Campaign\CampaignReadService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_fetch_campaign', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class FetchCampaignTool extends AbstractMcpTool
{
    public function __construct(
        private CampaignReadService $campaignReadService,
    ) {
    }

    /**
     * Fetch normalized details for a single campaign.
     */
    #[McpTool(name: 'mautic_fetch_campaign', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(int $campaignId, bool $includeEvents = true): array
    {
        $this->bootstrapExecution();

        return $this->campaignReadService->fetch($campaignId, $includeEvents);
    }
}
