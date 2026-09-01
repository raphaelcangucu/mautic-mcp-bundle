<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Meta\MetaService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_read_meta', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadMetaTool extends AbstractMcpTool
{
    public function __construct(
        private MetaService $service
    ) {}

    /**
     * Read Meta connections, assets, WhatsApp templates, contact identities, messages, or outbound queue jobs.
     */
    #[McpTool(name: 'mautic_read_meta', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['connections', 'assets', 'templates', 'identities', 'messages', 'queue'])] string $resource, ?int $id = null, int $page = 1, int $limit = 20): array
    {
        $this->bootstrapExecution();

        return $this->service->read($resource, $id, $page, $limit);
    }
}
