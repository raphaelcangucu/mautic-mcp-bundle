<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Webhook;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Application\Webhook\WebhookService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_webhooks', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageWebhooksTool extends AbstractMcpTool
{
    public function __construct(
        private WebhookService $service,
        private MutationExecutor $mutations
    ) {}

    /**
     * Create/update webhooks, or explicitly confirmed delete/test operations.
     */
    #[McpTool(name: 'mautic_manage_webhooks', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create', 'update', 'delete', 'test'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: true)] array $data = [], bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null, ?string $expectedDateModified = null): array
    {
        $this->bootstrapExecution();
        $payload = compact('action', 'id', 'data', 'confirm', 'expectedDateModified');
        if ($dryRun) {
            return $this->mutations->dryRun('webhooks', $action, $payload);
        }

        return $this->mutations->execute('webhooks', $idempotencyKey, $payload, fn (): array => $this->service->write($action, $id, $data, $confirm, $expectedDateModified));
    }
}
