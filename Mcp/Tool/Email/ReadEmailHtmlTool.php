<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Email;

use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_read_email_html', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::EMAIL_HTML)]
final class ReadEmailHtmlTool extends AbstractMcpTool
{
    public function __construct(
        private MauticManagementService $service,
    ) {}

    /**
     * Fetch the complete current HTML source of one Mautic email before editing it. Returns customHtml, plainText, preheaderText, template, publicPreview, previewUrl, and dateModified.
     */
    #[McpTool(name: 'mautic_read_email_html', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::EMAIL_HTML)]
    public function __invoke(int $emailId): array
    {
        $this->bootstrapExecution();
        $result = $this->service->emails('get', $emailId, [], [], [], '', 1, 1, false);
        $email = $result['email'];

        return [
            'emailId'       => (int) $email['id'],
            'name'          => (string) $email['name'],
            'subject'       => (string) $email['subject'],
            'customHtml'    => (string) ($email['customHtml'] ?? ''),
            'plainText'     => $email['plainText'] ?? null,
            'preheaderText' => $email['preheaderText'] ?? null,
            'template'      => $email['template'] ?? null,
            'publicPreview' => (bool) ($email['publicPreview'] ?? false),
            'previewUrl'    => (string) ($email['previewUrl'] ?? ''),
            'dateModified'  => $email['dateModified'] ?? null,
        ];
    }
}
