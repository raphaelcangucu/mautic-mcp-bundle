<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Form;

use MauticPlugin\MauticMcpBundle\Application\Form\FormReadService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_read_forms', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadFormsTool extends AbstractMcpTool
{
    public function __construct(
        private FormReadService $service
    ) {}

    /**
     * Read forms with fields/actions, or list submissions globally, by form, or by contact.
     */
    #[McpTool(name: 'mautic_read_forms', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['list', 'get', 'submissions', 'contact_submissions'])] string $action = 'list', ?int $formId = null, ?int $contactId = null, int $page = 1, int $limit = 50): array
    {
        $this->bootstrapExecution();

        return $this->service->read($action, $formId, $contactId, $page, $limit);
    }
}
