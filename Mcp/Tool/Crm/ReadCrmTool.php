<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Crm;

use MauticPlugin\MauticMcpBundle\Application\Crm\CrmService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_read_crm', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadCrmTool extends AbstractMcpTool
{
    public function __construct(
        private CrmService $service
    ) {}

    /**
     * Read companies, custom fields, tags, or stages.
     */
    #[McpTool(name: 'mautic_read_crm', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['companies', 'fields', 'tags', 'stages'])] string $resource, ?int $id = null, string $query = '', int $limit = 20, int $page = 1): array
    {
        $this->bootstrapExecution();

        return $this->service->read($resource, $id, $query, $limit, $page);
    }
}
