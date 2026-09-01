<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Crm;

use MauticPlugin\MauticMcpBundle\Application\Crm\CrmService;
use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_crm', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageCrmTool extends AbstractMcpTool
{
    public function __construct(
        private CrmService $service,
        private MutationExecutor $mutations
    ) {}

    /**
     * Write companies/associations, fields, tags, points, stages, or merge contacts.
     */
    #[McpTool(name: 'mautic_manage_crm', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create_company', 'update_company', 'add_company_contacts', 'remove_company_contacts', 'create_tag', 'add_tags', 'remove_tags', 'create_field', 'update_field', 'set_points', 'set_stage', 'merge_contacts'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: true)] array $data = [], #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)] array $contactIds = [], ?int $winnerContactId = null, ?int $loserContactId = null, bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null, ?string $expectedDateModified = null): array
    {
        $this->bootstrapExecution();
        $payload = compact('action', 'id', 'data', 'contactIds', 'winnerContactId', 'loserContactId', 'confirm', 'expectedDateModified');
        if ($dryRun) {
            return $this->mutations->dryRun('crm', $action, $payload);
        }

        return $this->mutations->execute('crm', $idempotencyKey, $payload, fn (): array => $this->service->write($action, $id, $data, $contactIds, $winnerContactId, $loserContactId, $confirm, $expectedDateModified));
    }
}
