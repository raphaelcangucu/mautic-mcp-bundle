<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Management;

use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_campaigns', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageCampaignsTool extends AbstractMcpTool
{
    public function __construct(
        private MauticManagementService $service,
        private MutationExecutor $mutations,
    ) {}

    /**
     * Write campaigns. Actions: create, update, delete, publish, unpublish, add_contacts, remove_contacts, set_segments.
     */
    #[McpTool(name: 'mautic_manage_campaigns', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create', 'update', 'delete', 'publish', 'unpublish', 'add_contacts', 'remove_contacts', 'set_segments'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: true)] array $data = [], #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)] array $contactIds = [], #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)] array $segmentIds = [], string $query = '', int $limit = 20, int $page = 1, bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null, ?string $expectedDateModified = null): array
    {
        $this->bootstrapExecution();

        if (in_array($action, ['list', 'get'], true)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Read actions moved to mautic_search_campaigns and mautic_fetch_campaign.');
        }

        $payload = compact('action', 'id', 'data', 'contactIds', 'segmentIds', 'confirm', 'expectedDateModified');
        if ($dryRun) {
            return $this->mutations->dryRun('campaign', $action, $payload);
        }

        return $this->mutations->execute('campaigns', $idempotencyKey, $payload, fn (): array => $this->service->campaigns($action, $id, $data, $contactIds, $segmentIds, $query, $limit, $page, $confirm, $expectedDateModified));
    }
}
