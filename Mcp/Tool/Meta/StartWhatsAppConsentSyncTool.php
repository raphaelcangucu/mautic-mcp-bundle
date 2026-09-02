<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use MauticPlugin\MauticMetaBundle\Application\Consent\WhatsAppConsentSyncService;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'start_whatsapp_consent_sync', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class StartWhatsAppConsentSyncTool extends AbstractMcpTool
{
    public function __construct(
        private WhatsAppConsentSyncService $sync,
        private MutationExecutor $mutations
    ) {}

    /**
     * Start a confirmed, resumable synchronization after preview. Never sends messages.
     */
    #[McpTool(name: 'start_whatsapp_consent_sync', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(int $assetId, string $source, string $consentVersion, int $batchSize = 100, bool $onlyUnsynced = true, bool $confirm = false, ?string $idempotencyKey = null): array
    {
        $this->bootstrapExecution();
        if (!$confirm || '' === trim((string) $idempotencyKey)) {
            return ['status' => 'rejected', 'errors' => [['field' => !$confirm ? 'confirm' : 'idempotencyKey', 'message' => !$confirm ? 'confirm=true is required.' : 'idempotencyKey is required.']]];
        }

        return $this->mutations->execute('whatsapp_consent_sync', $idempotencyKey, compact('assetId', 'source', 'consentVersion', 'batchSize', 'onlyUnsynced'), function () use ($assetId, $source, $consentVersion, $batchSize, $onlyUnsynced, $idempotencyKey): array {
            $run = $this->sync->start($assetId, $source, $consentVersion, $batchSize, $onlyUnsynced, (string) $idempotencyKey);
            return $this->sync->serialize($run);
        });
    }
}
