<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Application\Meta\MetaService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_meta', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageMetaTool extends AbstractMcpTool
{
    public function __construct(
        private MetaService $service,
        private MutationExecutor $mutations
    ) {}

    /**
     * Manage Meta connections/assets/templates and contact consent/linking, or test a connection.
     */
    #[McpTool(name: 'mautic_manage_meta', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: true), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(#[Schema(enum: ['create_connection', 'update_connection', 'delete_connection', 'create_asset', 'update_asset', 'delete_asset', 'create_template', 'update_template', 'delete_template', 'sync_templates', 'set_consent', 'link_identity', 'test_connection'])] string $action, ?int $id = null, #[Schema(type: 'object', additionalProperties: false, properties: [
        'status' => ['type' => 'string', 'enum' => ['unknown', 'opted_in', 'opted_out']],
        'contactId' => ['type' => ['integer', 'null']],
        'name' => ['type' => 'string'], 'app_id' => ['type' => 'string'], 'app_secret' => ['type' => 'string'],
        'access_token' => ['type' => 'string'], 'verify_token' => ['type' => 'string'], 'graph_version' => ['type' => 'string'],
        'webhook_adapters_json' => ['type' => 'string'], 'consent_source_url' => ['type' => 'string'], 'consent_source_secret' => ['type' => 'string'],
        'external_id' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => ['whatsapp_business_account', 'whatsapp_phone_number', 'instagram_account', 'facebook_page']],
        'username' => ['type' => ['string', 'null']], 'phone_number' => ['type' => ['string', 'null']], 'is_default' => ['type' => 'boolean'],
        'businessAccountId' => ['type' => 'integer'], 'language' => ['type' => 'string'], 'category' => ['type' => 'string'],
        'components' => ['type' => 'array', 'items' => ['type' => 'object']], 'settings' => ['type' => 'object'],
    ])] array $data = [], bool $confirm = false, bool $dryRun = false, ?string $idempotencyKey = null): array
    {
        $this->bootstrapExecution();
        $payload = compact('action', 'id', 'data', 'confirm');
        if ($dryRun) { return $this->mutations->dryRun('meta', $action, $this->redactCredentials($payload)); }

        return $this->mutations->execute('meta', $idempotencyKey, $payload, function () use ($action, $id, $data, $confirm): array {
            try {
                return $this->service->manage($action, $id, $data, $confirm);
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
                return ['status' => 'rejected', 'error' => ['field' => 'id', 'type' => 'not_found', 'message' => $exception->getMessage()]];
            } catch (\InvalidArgumentException|\DomainException|\Symfony\Component\HttpKernel\Exception\BadRequestHttpException $exception) {
                $field = 'link_identity' === $action && !array_key_exists('contactId', $data) ? 'data.contactId' : 'data';

                return ['status' => 'rejected', 'error' => ['field' => $field, 'type' => 'validation', 'message' => $exception->getMessage()]];
            }
        });
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function redactCredentials(array $payload): array
    {
        if (!is_array($payload['data'] ?? null)) {
            return $payload;
        }
        foreach (['app_secret', 'access_token', 'verify_token'] as $field) {
            if (array_key_exists($field, $payload['data'])) {
                $payload['data'][$field] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
