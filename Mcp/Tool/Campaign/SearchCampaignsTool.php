<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Campaign;

use MauticPlugin\MauticMcpBundle\Application\Campaign\CampaignReadService;
use MauticPlugin\MauticMcpBundle\Application\Campaign\CampaignSearchQuery;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_search_campaigns', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class SearchCampaignsTool extends AbstractMcpTool
{
    public function __construct(
        private CampaignReadService $campaignReadService,
    ) {
    }

    /**
     * Search campaigns by name or keyword.
     */
    #[McpTool(name: 'mautic_search_campaigns', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(string $query = '', int $limit = 10, int $page = 1): array
    {
        $this->bootstrapExecution();

        return $this->campaignReadService->search(new CampaignSearchQuery($query, $limit, $page));
    }
}
