<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use MauticPlugin\MauticMetaBundle\Application\Consent\WhatsAppConsentSyncService;
use MauticPlugin\MauticMetaBundle\Entity\MetaConsentSyncRun;
use MauticPlugin\MauticMetaBundle\Entity\MetaConsentSyncRunRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'get_whatsapp_consent_sync_status', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class GetWhatsAppConsentSyncStatusTool extends AbstractMcpTool
{
    public function __construct(
        private MetaConsentSyncRunRepository $runs,
        private WhatsAppConsentSyncService $sync
    ) {}

    /**
     * Get persistent progress and counters for a consent synchronization.
     */
    #[McpTool(name: 'get_whatsapp_consent_sync_status', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(int $runId): array
    {
        $this->bootstrapExecution();
        $run = $this->runs->find($runId);
        return $run instanceof MetaConsentSyncRun ? $this->sync->serialize($run) : ['status' => 'rejected', 'error' => ['field' => 'runId', 'type' => 'not_found', 'message' => 'Synchronization run was not found.']];
    }
}
