<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Management;

use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_contacts', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageContactsTool extends AbstractMcpTool
{
    public function __construct(
        private MauticManagementService $service,
        private MutationExecutor $mutations,
    ) {}

    /**
     * Write Mautic contacts. Actions: create, update, delete. Pass contact fields in data. Delete requires confirm=true.
     */
    #[McpTool(name: 'mautic_manage_contacts', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create', 'update', 'delete'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: true)] array $data = [], string $query = '', int $limit = 20, int $page = 1, bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null, ?string $expectedDateModified = null): array
    {
        $this->bootstrapExecution();

        if (in_array($action, ['list', 'get'], true)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Read actions moved to mautic_search_contacts and mautic_fetch_contact.');
        }

        $payload = compact('action', 'id', 'data', 'confirm', 'expectedDateModified');
        if ($dryRun) {
            return $this->mutations->dryRun('contact', $action, $payload);
        }

        return $this->mutations->execute('contacts', $idempotencyKey, $payload, fn (): array => $this->service->contacts($action, $id, $data, $query, $limit, $page, $confirm, $expectedDateModified));
    }
}
