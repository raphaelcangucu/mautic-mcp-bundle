<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Management;

use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[McpTool(name: 'mautic_read_emails', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadEmailsTool extends AbstractMcpTool
{
    public function __construct(
        private MauticManagementService $service
    ) {}

    /**
     * List email metadata or get one email with its complete customHtml, plainText, preheader, preview URL/state, template, and lock state. Use mautic_read_email_html when only editable source is needed.
     */
    #[McpTool(name: 'mautic_read_emails', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[\Mcp\Capability\Attribute\Schema(enum: ['list', 'get'])] string $action = 'list', ?int $id = null, string $query = '', int $limit = 20, int $page = 1): array
    {
        $this->bootstrapExecution();
        if (!in_array($action, ['list', 'get'], true)) {
            throw new BadRequestHttpException('Unsupported read action. Use list or get.');
        }

        return $this->service->emails($action, $id, [], [], [], $query, $limit, $page, false);
    }
}
