<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Management;

use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_emails', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageEmailsTool extends AbstractMcpTool
{
    public function __construct(
        private MauticManagementService $service,
        private MutationExecutor $mutations,
    ) {}

    /**
     * Create, edit HTML, control public preview and editing locks, clone, configure A/B variants, publish, send, or delete emails.
     */
    #[McpTool(name: 'mautic_manage_emails', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create', 'update', 'update_html', 'delete', 'publish', 'unpublish', 'enable_preview', 'disable_preview', 'lock', 'unlock', 'set_segments', 'clone', 'create_ab_test', 'send_test', 'send_to_contacts', 'send_to_segments'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: true)] array $data = [], #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)] array $contactIds = [], #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)] array $segmentIds = [], string $query = '', int $limit = 20, int $page = 1, bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null, ?string $expectedDateModified = null): array
    {
        $this->bootstrapExecution();

        if (in_array($action, ['list', 'get'], true)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Read actions moved to mautic_read_emails.');
        }

        $payload = compact('action', 'id', 'data', 'contactIds', 'segmentIds', 'confirm', 'expectedDateModified');
        if ($dryRun) {
            return $this->mutations->dryRun('email', $action, $payload);
        }

        return $this->mutations->execute('emails', $idempotencyKey, $payload, fn (): array => $this->service->emails($action, $id, $data, $contactIds, $segmentIds, $query, $limit, $page, $confirm, $expectedDateModified));
    }
}
