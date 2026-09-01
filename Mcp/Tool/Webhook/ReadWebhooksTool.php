<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Webhook;

use MauticPlugin\MauticMcpBundle\Application\Webhook\WebhookService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_read_webhooks', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadWebhooksTool extends AbstractMcpTool
{
    public function __construct(
        private WebhookService $service
    ) {}

    /**
     * Read webhooks, supported triggers, and incremental delivery logs.
     */
    #[McpTool(name: 'mautic_read_webhooks', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['list', 'get', 'events', 'incremental'])] string $action = 'list', ?int $id = null, ?int $afterId = null, int $page = 1, int $limit = 50): array
    {
        $this->bootstrapExecution();

        return $this->service->read($action, $id, $afterId, $page, $limit);
    }
}
