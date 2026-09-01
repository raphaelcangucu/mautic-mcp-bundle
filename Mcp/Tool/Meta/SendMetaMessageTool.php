<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Application\Meta\MetaService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_send_meta_message', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class SendMetaMessageTool extends AbstractMcpTool
{
    public function __construct(
        private MetaService $service,
        private MutationExecutor $mutations
    ) {}

    /**
     * Queue an official WhatsApp text/template or Instagram reply/DM with automatic retries.
     */
    #[McpTool(name: 'mautic_send_meta_message', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['whatsapp', 'instagram'])] string $channel, #[Schema(enum: ['text', 'template', 'media', 'interactive', 'private_reply', 'public_reply', 'direct_message'])] string $action, int $assetId, string $recipient, #[Schema(type: 'object', additionalProperties: true)] array $data = [], ?int $contactId = null, bool $queue = true, int $maxAttempts = 5, bool $dryRun = false, ?string $idempotencyKey = null): array
    {
        $this->bootstrapExecution();
        $payload = compact('channel', 'action', 'assetId', 'recipient', 'data', 'contactId', 'queue', 'maxAttempts');
        if ($dryRun) { return $this->mutations->dryRun('meta_message', 'send', $payload); }

        return $this->mutations->execute('meta_message', $idempotencyKey, $payload, fn (): array => $this->service->send($channel, $action, $assetId, $recipient, $data, $contactId, $queue, $maxAttempts));
    }
}
