<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Contact;

use MauticPlugin\MauticMcpBundle\Application\Contact\ContactReadService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_fetch_contact', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class FetchContactTool extends AbstractMcpTool
{
    public function __construct(
        private ContactReadService $contactReadService,
    ) {
    }

    /**
     * Fetch normalized details for a single contact.
     */
    #[McpTool(name: 'mautic_fetch_contact', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(int $contactId): array
    {
        $this->bootstrapExecution();

        return $this->contactReadService->fetch($contactId);
    }
}
