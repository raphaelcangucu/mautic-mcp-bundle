<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Campaign;

use MauticPlugin\MauticMcpBundle\Application\Campaign\CampaignFlowService;
use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[McpTool(name: 'mautic_write_campaign_flow', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageCampaignFlowTool extends AbstractMcpTool
{
    public function __construct(
        private CampaignFlowService $service,
        private MutationExecutor $mutations,
    ) {}

    /**
     * Replace a complete campaign graph. Events use key, name, type, eventType, parent, path, properties, trigger, and position. Requires confirm=true.
     */
    #[McpTool(name: 'mautic_write_campaign_flow', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(
        #[Schema(enum: ['replace', 'delete_events'])]
        string $action,
        ?int $campaignId = null,
        #[Schema(type: 'array', items: ['type' => 'object', 'additionalProperties' => true])]
        array $events = [],
        #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)]
        array $eventIds = [],
        ?string $eventType = null,
        bool $cascade = false,
        bool $confirm = false,
        bool $dryRun = false,
        ?string $idempotencyKey = null,
        ?string $expectedDateModified = null,
    ): array {
        $this->bootstrapExecution();
        $payload = compact('action', 'campaignId', 'events', 'eventIds', 'eventType', 'cascade', 'confirm', 'expectedDateModified');
        if ($dryRun) {
            return $this->mutations->dryRun('campaign_flow', $action, $payload);
        }

        return $this->mutations->execute('campaign_flow', $idempotencyKey, $payload, fn (): array => match ($action) {
            'replace' => $this->service->replaceFlow($this->requireCampaignId($campaignId), $events, $confirm, $expectedDateModified),
            'delete_events' => $this->service->deleteEvents($this->requireCampaignId($campaignId), $eventIds, $cascade, $confirm, $expectedDateModified),
            default => throw new BadRequestHttpException('Unsupported write action. Use replace or delete_events; reads moved to mautic_read_campaign_flow.'),
        });
    }

    private function requireCampaignId(?int $campaignId): int
    {
        if (null === $campaignId || $campaignId < 1) {
            throw new BadRequestHttpException('campaignId is required for this action.');
        }

        return $campaignId;
    }
}
