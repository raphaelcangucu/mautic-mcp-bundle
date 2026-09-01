<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Contact;

use MauticPlugin\MauticMcpBundle\Application\Contact\ContactReadService;
use MauticPlugin\MauticMcpBundle\Application\Contact\ContactSearchQuery;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_search_contacts', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class SearchContactsTool extends AbstractMcpTool
{
    public function __construct(
        private ContactReadService $contactReadService,
    ) {
    }

    /**
     * Search contacts by name, email, or other indexed fields.
     */
    #[McpTool(name: 'mautic_search_contacts', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(string $query = '', int $limit = 10, int $page = 1): array
    {
        $this->bootstrapExecution();

        return $this->contactReadService->search(new ContactSearchQuery($query, $limit, $page));
    }
}
