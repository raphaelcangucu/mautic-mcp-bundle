<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Management;

use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[McpTool(name: 'mautic_read_segments', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadSegmentsTool extends AbstractMcpTool
{
    public function __construct(
        private MauticManagementService $service
    ) {}

    /**
     * Read segments or their active contact members. Actions: list, get, members.
     * Members are paginated newest-first by contact ID and include profile fields
     * such as phone and mobile. metaOptedIn is optional and Meta-only: omit it to
     * return every member, true for opted-in, or false for not opted-in.
     */
    #[McpTool(name: 'mautic_read_segments', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[\Mcp\Capability\Attribute\Schema(enum: ['list', 'get', 'members'])] string $action = 'list', ?int $id = null, string $query = '', int $limit = 20, int $page = 1, #[\Mcp\Capability\Attribute\Schema(description: 'Optional Meta-only filter for action=members. Omit/null for all members; true for opted-in; false for not opted-in, including contacts without a recorded Meta identity.')] ?bool $metaOptedIn = null): array
    {
        $this->bootstrapExecution();
        if (!in_array($action, ['list', 'get', 'members'], true)) {
            throw new BadRequestHttpException('Unsupported read action. Use list, get, or members.');
        }
        if ('members' !== $action && null !== $metaOptedIn) {
            throw new BadRequestHttpException('metaOptedIn can only be used with action=members.');
        }

        return $this->service->segments($action, $id, [], [], $query, $limit, $page, false, null, $metaOptedIn);
    }
}
