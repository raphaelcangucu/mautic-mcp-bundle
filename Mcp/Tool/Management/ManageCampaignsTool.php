<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Management;

use MauticPlugin\MauticMcpBundle\Application\Campaign\MetaCampaignCreationService;
use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_campaigns', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageCampaignsTool extends AbstractMcpTool
{
    public function __construct(
        private MauticManagementService $service,
        private MetaCampaignCreationService $metaCampaigns,
        private MutationExecutor $mutations,
    ) {}

    /**
     * Write campaigns. create_instagram_comment creates a draft with an exact account/media/keyword decision and a private reply. create_whatsapp_template creates a draft with a selected WhatsApp phone asset and approved template. Both require confirm=true and accept campaign fields in data plus data.instagramComment or data.whatsapp.
     */
    #[McpTool(name: 'mautic_manage_campaigns', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(
        #[Schema(enum: ['create', 'create_instagram_comment', 'create_whatsapp_template', 'update', 'delete', 'publish', 'unpublish', 'add_contacts', 'remove_contacts', 'set_segments'])]
        string $action,
        ?int $id = null,
        #[Schema(type: 'object', additionalProperties: true, description: 'Campaign fields: name, description, allowRestart, publishUp, publishDown. For create_instagram_comment add instagramComment={assetId,mediaId,keyword,privateReply}. For create_whatsapp_template add whatsapp={assetId,templateName,language,phoneField,bodyParameters,queue,maxAttempts}.')]
        array $data = [],
        #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)]
        array $contactIds = [],
        #[Schema(type: 'array', items: ['type' => 'integer'], uniqueItems: true)]
        array $segmentIds = [],
        string $query = '',
        int $limit = 20,
        int $page = 1,
        bool $confirm = false,
        bool $dryRun = false,
        ?string $idempotencyKey = null,
        ?string $expectedDateModified = null,
    ): array
    {
        $this->bootstrapExecution();

        if (in_array($action, ['list', 'get'], true)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Read actions moved to mautic_search_campaigns and mautic_fetch_campaign.');
        }

        $payload = compact('action', 'id', 'data', 'contactIds', 'segmentIds', 'confirm', 'expectedDateModified');
        $metaCreation = in_array($action, [MetaCampaignCreationService::INSTAGRAM_COMMENT, MetaCampaignCreationService::WHATSAPP_TEMPLATE], true);
        if ($metaCreation && (null !== $id || [] !== $contactIds)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Meta campaign creation does not accept id or contactIds; use segmentIds or add contacts after creation.');
        }
        if ($dryRun) {
            if ($metaCreation) {
                return ['status' => 'dry_run', 'dryRun' => true, 'wouldWrite' => true]
                    + $this->metaCampaigns->preview($action, $data, $segmentIds);
            }

            return $this->mutations->dryRun('campaign', $action, $payload);
        }

        return $this->mutations->execute('campaigns', $idempotencyKey, $payload, fn (): array => $metaCreation
            ? $this->metaCampaigns->create($action, $data, $segmentIds, $confirm)
            : $this->service->campaigns($action, $id, $data, $contactIds, $segmentIds, $query, $limit, $page, $confirm, $expectedDateModified));
    }
}
