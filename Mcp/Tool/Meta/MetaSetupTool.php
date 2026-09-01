<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Meta\MetaSetupService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_meta_setup', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class MetaSetupTool extends AbstractMcpTool
{
    public function __construct(
        private MetaSetupService $setup
    ) {}

    /**
     * Get a safe, contextual setup guide and current configuration status for the official Meta plugin, including connections, assets, webhooks, campaigns, queues, permissions, MCP usage, and troubleshooting. Call section=status first.
     */
    #[McpTool(name: 'mautic_meta_setup', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['all', 'status', 'installation', 'meta_app', 'connections', 'assets', 'webhooks', 'campaigns', 'queue', 'permissions', 'mcp', 'troubleshooting'])] string $section = 'all', ?int $connectionId = null): array
    {
        $this->bootstrapExecution();

        return $this->setup->guide($section, $connectionId);
    }
}
