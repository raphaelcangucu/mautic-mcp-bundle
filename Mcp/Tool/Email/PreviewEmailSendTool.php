<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Email;

use MauticPlugin\MauticMcpBundle\Application\Email\EmailPreviewService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_preview_email_send', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class PreviewEmailSendTool extends AbstractMcpTool
{
    public function __construct(
        private EmailPreviewService $service
    ) {}

    /**
     * Safely preview an email send without dispatching it. Resolves recipients, exclusions, render samples, and missing tokens.
     */
    #[McpTool(name: 'mautic_preview_email_send', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(int $emailId, #[\Mcp\Capability\Attribute\Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)] array $contactIds = [], #[\Mcp\Capability\Attribute\Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)] array $segmentIds = [], int $sampleSize = 3): array
    {
        $this->bootstrapExecution();

        return $this->service->preview($emailId, $contactIds, $segmentIds, $sampleSize);
    }
}
