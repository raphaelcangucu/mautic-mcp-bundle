<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use MauticPlugin\MauticMetaBundle\Entity\MetaConsentSyncRun;
use MauticPlugin\MauticMetaBundle\Entity\MetaConsentSyncRunRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'list_whatsapp_consent_sync_rejections', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ListWhatsAppConsentSyncRejectionsTool extends AbstractMcpTool
{
    public function __construct(
        private MetaConsentSyncRunRepository $runs
    ) {}

    /**
     * List rejection and conflict reasons without credentials or tokens.
     */
    #[McpTool(name: 'list_whatsapp_consent_sync_rejections', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(int $runId, int $page = 1, int $limit = 100): array
    {
        $this->bootstrapExecution();
        $run = $this->runs->find($runId);
        if (!$run instanceof MetaConsentSyncRun) {
            return ['status' => 'rejected', 'error' => ['field' => 'runId', 'type' => 'not_found', 'message' => 'Synchronization run was not found.']];
        }
        $limit = min(500, max(1, $limit));
        $page = max(1, $page);
        $all = $run->getRejections();
        return ['status' => 'success', 'items' => array_slice($all, ($page - 1) * $limit, $limit), 'page' => $page, 'limit' => $limit, 'total' => count($all), 'hasMore' => $page * $limit < count($all)];
    }
}
