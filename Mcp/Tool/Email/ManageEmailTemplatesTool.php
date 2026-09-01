<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Email;

use MauticPlugin\MauticMcpBundle\Application\Email\EmailTemplateService;
use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_email_templates', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageEmailTemplatesTool extends AbstractMcpTool
{
    public function __construct(
        private EmailTemplateService $service,
        private MutationExecutor $mutations,
    ) {}

    /**
     * Create, edit, remove, publish, preview-lock, or editing-lock reusable email templates. Delete/unlock require confirm=true.
     */
    #[McpTool(name: 'mautic_manage_email_templates', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create', 'update', 'update_html', 'delete', 'publish', 'unpublish', 'enable_preview', 'disable_preview', 'lock', 'unlock'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: true)] array $data = [], bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null, ?string $expectedDateModified = null): array
    {
        $this->bootstrapExecution();
        $payload = compact('action', 'id', 'data', 'confirm', 'expectedDateModified');
        if ($dryRun) {
            return $this->mutations->dryRun('email_template', $action, $payload);
        }

        return $this->mutations->execute('email_templates', $idempotencyKey, $payload, fn (): array => $this->service->write($action, $id, $data, $confirm, $expectedDateModified));
    }
}
