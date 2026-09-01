<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Tag;

use MauticPlugin\MauticMcpBundle\Application\Tag\TagService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_read_tags', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadTagsTool extends AbstractMcpTool
{
    public function __construct(
        private TagService $service
    ) {}

    /**
     * List or fetch tags, including contact counts.
     */
    #[McpTool(name: 'mautic_read_tags', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['list', 'get'])] string $action = 'list', ?int $id = null, string $query = '', int $page = 1, int $limit = 50): array
    {
        $this->bootstrapExecution();

        return $this->service->read($action, $id, $query, $page, $limit);
    }
}
