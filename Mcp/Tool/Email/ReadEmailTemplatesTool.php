<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Email;

use MauticPlugin\MauticMcpBundle\Application\Email\EmailTemplateService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_read_email_templates', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadEmailTemplatesTool extends AbstractMcpTool
{
    public function __construct(
        private EmailTemplateService $service,
    ) {}

    /**
     * List or fetch reusable Mautic email templates, including their complete HTML and preview/lock state.
     */
    #[McpTool(name: 'mautic_read_email_templates', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['list', 'get'])] string $action = 'list', ?int $id = null, string $query = '', int $page = 1, int $limit = 20): array
    {
        $this->bootstrapExecution();

        return $this->service->read($action, $id, $query, $page, $limit);
    }
}
