<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Analytics;

use MauticPlugin\MauticMcpBundle\Application\Analytics\AnalyticsService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_analytics', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class AnalyticsTool extends AbstractMcpTool
{
    public function __construct(
        private AnalyticsService $service
    ) {}

    /**
     * Query analytics. Reports: campaign_performance, email_performance, contact_growth, segment_growth, contacts_by_source, contacts_by_tag.
     */
    #[McpTool(name: 'mautic_analytics', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(
        #[Schema(enum: ['campaign_performance', 'email_performance', 'contact_growth', 'segment_growth', 'contacts_by_source', 'contacts_by_tag'])]
        string $report,
        ?string $from = null,
        ?string $to = null,
        #[Schema(enum: ['day', 'week', 'month'])]
        string $groupBy = 'day',
        int $limit = 100,
        int $page = 1,
    ): array
    {
        $this->bootstrapExecution();

        return $this->service->query($report, $from, $to, $groupBy, $limit, $page);
    }
}
