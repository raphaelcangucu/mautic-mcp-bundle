<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Campaign;

use MauticPlugin\MauticMcpBundle\Application\Campaign\CampaignFlowService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[McpTool(name: 'mautic_read_campaign_flow', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadCampaignFlowTool extends AbstractMcpTool
{
    public function __construct(
        private CampaignFlowService $service
    ) {}

    /**
     * Read or validate campaign graphs. Actions: list_types, get, validate.
     */
    #[McpTool(name: 'mautic_read_campaign_flow', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(
        #[Schema(enum: ['list_types', 'get', 'validate'])]
        string $action,
        ?int $campaignId = null,
        #[Schema(type: 'array', items: ['type' => 'object', 'additionalProperties' => true])]
        array $events = [],
        #[Schema(enum: ['action', 'decision', 'condition'])]
        ?string $eventType = null,
    ): array {
        $this->bootstrapExecution();

        return match ($action) {
            'list_types' => $this->service->listTypes($eventType),
            'get'        => $this->service->getFlow($this->requireCampaignId($campaignId)),
            'validate'   => $this->service->validateFlow($events),
            default      => throw new BadRequestHttpException('Unsupported read action. Use list_types, get, or validate.'),
        };
    }

    private function requireCampaignId(?int $campaignId): int
    {
        if (null === $campaignId || $campaignId < 1) {
            throw new BadRequestHttpException('campaignId is required for this action.');
        }

        return $campaignId;
    }
}
