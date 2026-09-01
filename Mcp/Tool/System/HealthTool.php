<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\System;

use MauticPlugin\MauticMcpBundle\Application\System\HealthService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_health', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class HealthTool extends AbstractMcpTool
{
    public function __construct(
        private HealthService $service
    ) {}

    /**
     * Show Mautic health, version, authenticated user, and effective permissions.
     */
    #[McpTool(name: 'mautic_health', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(): array
    {
        $this->bootstrapExecution();

        return $this->service->status();
    }
}
