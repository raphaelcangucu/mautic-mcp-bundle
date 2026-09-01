<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Application\Meta\MetaService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_meta', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageMetaTool extends AbstractMcpTool
{
    public function __construct(
        private MetaService $service,
        private MutationExecutor $mutations
    ) {}

    /**
     * Manage Meta connections/assets/templates and contact consent/linking, or test a connection.
     */
    #[McpTool(name: 'mautic_manage_meta', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create_connection', 'update_connection', 'delete_connection', 'create_asset', 'update_asset', 'delete_asset', 'create_template', 'update_template', 'delete_template', 'sync_templates', 'set_consent', 'link_identity', 'test_connection'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: true)] array $data = [], bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null): array
    {
        $this->bootstrapExecution();
        $payload = compact('action', 'id', 'data', 'confirm');
        if ($dryRun) { return $this->mutations->dryRun('meta', $action, $this->redactCredentials($payload)); }

        return $this->mutations->execute('meta', $idempotencyKey, $payload, fn (): array => $this->service->manage($action, $id, $data, $confirm));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function redactCredentials(array $payload): array
    {
        if (!is_array($payload['data'] ?? null)) {
            return $payload;
        }
        foreach (['app_secret', 'access_token', 'verify_token'] as $field) {
            if (array_key_exists($field, $payload['data'])) {
                $payload['data'][$field] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
