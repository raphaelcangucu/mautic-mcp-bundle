<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use MauticPlugin\MauticMetaBundle\Application\Consent\WhatsAppConsentSyncService;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'preview_whatsapp_consent_sync', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class PreviewWhatsAppConsentSyncTool extends AbstractMcpTool
{
    public function __construct(
        private WhatsAppConsentSyncService $sync
    ) {}

    /**
     * Analyze persisted landing consent evidence without modifying the database.
     */
    #[McpTool(name: 'preview_whatsapp_consent_sync', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(int $assetId, string $source, string $consentVersion, int $batchSize = 100, bool $onlyUnsynced = true, bool $dryRun = true): array
    {
        $this->bootstrapExecution();

        return $this->sync->preview($assetId, $source, $consentVersion, $batchSize, $onlyUnsynced) + ['dryRun' => true];
    }
}
