<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use MauticPlugin\MauticMetaBundle\Application\Consent\WhatsAppConsentSyncService;
use MauticPlugin\MauticMetaBundle\Entity\MetaConsentSyncRun;
use MauticPlugin\MauticMetaBundle\Entity\MetaConsentSyncRunRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'cancel_whatsapp_consent_sync', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class CancelWhatsAppConsentSyncTool extends AbstractMcpTool
{
    public function __construct(
        private MetaConsentSyncRunRepository $runs,
        private WhatsAppConsentSyncService $sync,
        private MutationExecutor $mutations
    ) {}

    /**
     * Cancel a run safely at its last completed checkpoint.
     */
    #[McpTool(name: 'cancel_whatsapp_consent_sync', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(int $runId, bool $confirm = false, ?string $idempotencyKey = null): array
    {
        $this->bootstrapExecution();
        if (!$confirm || '' === trim((string) $idempotencyKey)) {
            return ['status' => 'rejected', 'errors' => [['field' => !$confirm ? 'confirm' : 'idempotencyKey', 'message' => !$confirm ? 'confirm=true is required.' : 'idempotencyKey is required.']]];
        }
        return $this->mutations->execute('cancel_whatsapp_consent_sync', $idempotencyKey, ['runId' => $runId], function () use ($runId): array {
            $run = $this->runs->find($runId);
            if (!$run instanceof MetaConsentSyncRun) {
                return ['status' => 'rejected', 'error' => ['field' => 'runId', 'type' => 'not_found', 'message' => 'Synchronization run was not found.']];
            }
            $this->sync->cancel($run);
            return $this->sync->serialize($run);
        });
    }
}
