<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Meta\MetaService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[McpTool(name: 'mautic_read_meta', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ReadMetaTool extends AbstractMcpTool
{
    public function __construct(
        private MetaService $service
    ) {}

    /**
     * Read Meta connections, assets, WhatsApp templates, contact identities, messages, or outbound queue jobs.
     * For identities, contactIds can batch-resolve consent after reading segment members.
     */
    #[McpTool(name: 'mautic_read_meta', annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['connections', 'assets', 'templates', 'identities', 'messages', 'queue'])] string $resource, ?int $id = null, int $page = 1, int $limit = 20, #[Schema(type: 'array', items: ['type' => 'integer', 'minimum' => 1], maxItems: 100, uniqueItems: true, description: 'Only valid when resource=identities and id is omitted. Omit for every other resource.')] array $contactIds = []): array
    {
        $this->bootstrapExecution();

        try {
            return $this->service->read($resource, $id, $page, $limit, $contactIds);
        } catch (NotFoundHttpException $exception) {
            return [
                'status'   => 'rejected',
                'resource' => $resource,
                'error'    => [
                    'field'   => 'id',
                    'type'    => 'not_found',
                    'message' => $exception->getMessage(),
                ],
            ];
        } catch (BadRequestHttpException|\InvalidArgumentException|\DomainException $exception) {
            $field = str_contains($exception->getMessage(), 'contactIds') ? 'contactIds' : 'resource';

            return [
                'status'   => 'rejected',
                'resource' => $resource,
                'error'    => [
                    'field'   => $field,
                    'type'    => 'validation',
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }
}
