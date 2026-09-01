<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Tag;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Application\Tag\TagService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_tags', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageTagsTool extends AbstractMcpTool
{
    public function __construct(
        private TagService $service,
        private MutationExecutor $mutations
    ) {}

    /**
     * Create/update/delete tags or add/remove a tag from contacts.
     */
    #[McpTool(name: 'mautic_manage_tags', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create', 'update', 'delete', 'add_contacts', 'remove_contacts'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: true)] array $data = [], #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)] array $contactIds = [], bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null): array
    {
        $this->bootstrapExecution();
        $payload = compact('action', 'id', 'data', 'contactIds', 'confirm');
        if ($dryRun) {
            return $this->mutations->dryRun('tags', $action, $payload);
        }

        return $this->mutations->execute('tags', $idempotencyKey, $payload, fn (): array => $this->service->write($action, $id, $data, $contactIds, $confirm));
    }
}
