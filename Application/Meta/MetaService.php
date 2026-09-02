<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Meta;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticMetaBundle\Application\Connection\AssetManager;
use MauticPlugin\MauticMetaBundle\Application\Connection\ConnectionDiagnostic;
use MauticPlugin\MauticMetaBundle\Application\Connection\ConnectionManager;
use MauticPlugin\MauticMetaBundle\Application\Contact\IdentityManager;
use MauticPlugin\MauticMetaBundle\Application\Instagram\InstagramService;
use MauticPlugin\MauticMetaBundle\Application\Queue\OutboundQueue;
use MauticPlugin\MauticMetaBundle\Application\WhatsApp\WhatsAppTemplateManager;
use MauticPlugin\MauticMetaBundle\Domain\AssetType;
use MauticPlugin\MauticMetaBundle\Domain\ConsentStatus;
use MauticPlugin\MauticMetaBundle\Entity\MetaAsset;
use MauticPlugin\MauticMetaBundle\Entity\MetaAssetRepository;
use MauticPlugin\MauticMetaBundle\Entity\MetaConnection;
use MauticPlugin\MauticMetaBundle\Entity\MetaConnectionRepository;
use MauticPlugin\MauticMetaBundle\Entity\MetaContactIdentity;
use MauticPlugin\MauticMetaBundle\Entity\MetaContactIdentityRepository;
use MauticPlugin\MauticMetaBundle\Entity\MetaMessage;
use MauticPlugin\MauticMetaBundle\Entity\MetaMessageRepository;
use MauticPlugin\MauticMetaBundle\Entity\MetaOutboundJob;
use MauticPlugin\MauticMetaBundle\Entity\MetaOutboundJobRepository;
use MauticPlugin\MauticMetaBundle\Entity\WhatsAppTemplate;
use MauticPlugin\MauticMetaBundle\Entity\WhatsAppTemplateRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class MetaService
{
    public function __construct(
        private CorePermissions $permissions,
        private MetaConnectionRepository $connections,
        private MetaAssetRepository $assets,
        private WhatsAppTemplateRepository $templates,
        private MetaContactIdentityRepository $identities,
        private MetaMessageRepository $messages,
        private MetaOutboundJobRepository $jobs,
        private OutboundQueue $queue,
        private WhatsAppTemplateManager $templateManager,
        private IdentityManager $identityManager,
        private ConnectionDiagnostic $diagnostic,
        private ConnectionManager $connectionManager,
        private AssetManager $assetManager,
        private InstagramService $instagram,
        private LeadModel $leads,
    ) {
    }

    public function read(string $resource, ?int $id, int $page, int $limit): array
    {
        $permissionResource = match ($resource) {
            'connections', 'assets' => 'connections', 'templates' => 'templates', default => 'messages'
        };
        $this->assertPermission('view', $permissionResource);
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;
        [$repository, $normalizer] = match ($resource) {
            'connections' => [$this->connections, $this->normalizeConnection(...)],
            'assets' => [$this->assets, $this->normalizeAsset(...)],
            'templates' => [$this->templates, $this->normalizeTemplate(...)],
            'identities' => [$this->identities, $this->normalizeIdentity(...)],
            'messages' => [$this->messages, $this->normalizeMessage(...)],
            'queue' => [$this->jobs, $this->normalizeJob(...)],
            default => throw new BadRequestHttpException('Resource must be connections, assets, templates, identities, messages, or queue.'),
        };
        if (null !== $id) {
            $entity = $repository->find($id);
            if (null === $entity) {
                throw new NotFoundHttpException(sprintf('%s %d was not found.', $resource, $id));
            }

            return ['resource' => $resource, 'item' => $normalizer($entity)];
        }
        $total = $repository->count([]);
        $items = array_map($normalizer, $repository->findBy([], ['id' => 'DESC'], $limit, $offset));
        $hasMore = $offset + count($items) < $total;

        return ['resource' => $resource, 'page' => $page, 'limit' => $limit, 'count' => count($items), 'total' => $total, 'hasMore' => $hasMore, 'nextPage' => $hasMore ? $page + 1 : null, 'items' => $items];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function send(string $channel, string $action, int $assetId, string $recipient, array $data, ?int $contactId, bool $queue, int $maxAttempts): array
    {
        $this->assertPermission('create', 'messages');
        $asset = $this->asset($assetId);
        $contact = $this->contact($contactId);
        if ('' === trim($recipient)) {
            throw new BadRequestHttpException('recipient cannot be empty.');
        }
        if ('whatsapp' === $channel && AssetType::WhatsAppPhoneNumber !== $asset->getType()) {
            throw new BadRequestHttpException('assetId must reference a WhatsApp phone number.');
        }
        if ('instagram' === $channel && AssetType::InstagramAccount !== $asset->getType()) {
            throw new BadRequestHttpException('assetId must reference an Instagram account.');
        }
        $operation = match ($channel.':'.$action) {
            'whatsapp:text' => 'whatsapp_text', 'whatsapp:template' => 'whatsapp_template', 'whatsapp:media' => 'whatsapp_media', 'whatsapp:interactive' => 'whatsapp_interactive', 'instagram:private_reply' => 'instagram_private_reply', 'instagram:public_reply' => 'instagram_public_reply', 'instagram:direct_message' => 'instagram_direct_message', default => throw new BadRequestHttpException('Unsupported channel/action combination.')
        };
        $payload = ['recipient' => $recipient] + $data;
        if ('template' === $action && ('' === trim((string) ($data['name'] ?? '')) || '' === trim((string) ($data['language'] ?? '')) || !is_array($data['components'] ?? null))) {
            throw new BadRequestHttpException('WhatsApp template sends require data.name, data.language, and data.components.');
        }
        if ('media' === $action && ('' === trim((string) ($data['media_type'] ?? '')) || !is_array($data['media'] ?? null))) {
            throw new BadRequestHttpException('WhatsApp media sends require data.media_type and data.media.');
        }
        if ('interactive' === $action && !is_array($data['interactive'] ?? null)) {
            throw new BadRequestHttpException('WhatsApp interactive sends require data.interactive.');
        }
        if (!in_array($action, ['template', 'media', 'interactive'], true) && '' === trim((string) ($data['text'] ?? ''))) {
            throw new BadRequestHttpException('This Meta action requires data.text.');
        }
        if (!$queue) {
            throw new BadRequestHttpException('Immediate MCP sends are disabled; use queue=true for retryable delivery.');
        }
        $job = $this->queue->enqueue($asset, $operation, $payload, $contact, max(1, min(10, $maxAttempts)));

        return ['status' => 'queued', 'jobId' => $job->getId(), 'assetId' => $assetId, 'contactId' => $contact?->getId(), 'operation' => $operation];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function manage(string $action, ?int $id, array $data, bool $confirm): array
    {
        return match ($action) {
            'create_template' => $this->createTemplate($data),
            'update_template' => $this->updateTemplate($id, $data),
            'delete_template' => $this->deleteTemplate($id, $confirm),
            'sync_templates' => $this->syncTemplates($id),
            'set_consent' => $this->setConsent($id, $data),
            'link_identity' => $this->linkIdentity($id, $data),
            'test_connection' => $this->testConnection($id, $confirm),
            'create_connection' => $this->createConnection($data),
            'update_connection' => $this->updateConnection($id, $data),
            'delete_connection' => $this->deleteConnection($id, $confirm),
            'create_asset' => $this->createAsset($id, $data),
            'update_asset' => $this->updateAsset($id, $data),
            'delete_asset' => $this->deleteAsset($id, $confirm),
            default => throw new BadRequestHttpException('Unsupported Meta management action.'),
        };
    }

    /**
     * Read live data from the official Instagram Graph API.
     *
     * @param list<string> $metrics
     *
     * @return array<string, mixed>
     */
    public function readApi(string $action, int $assetId, ?string $resourceId, array $metrics, int $limit, ?string $after): array
    {
        $this->assertPermission('view', 'instagram_insights' === $action ? 'analytics' : 'messages');
        $asset = $this->asset($assetId);
        $result = match ($action) {
            'instagram_profile' => $this->instagram->profile($asset),
            'instagram_media' => $this->instagram->media($asset, $limit, $after),
            'instagram_comments' => $this->instagram->comments($asset, $this->requiredResourceId($resourceId, 'media'), $limit, $after),
            'instagram_insights' => $this->instagram->insights($asset, $this->requiredResourceId($resourceId, 'media'), $metrics),
            'instagram_conversations' => $this->instagram->conversations($asset, $limit, $after),
            'instagram_conversation_messages' => $this->instagram->conversationMessages($asset, $this->requiredResourceId($resourceId, 'conversation')),
            default => throw new BadRequestHttpException('Unsupported Meta API read action.'),
        };

        return ['action' => $action, 'assetId' => $assetId, 'data' => $result];
    }

    private function createTemplate(array $data): array
    {
        $this->assertPermission('create', 'templates');
        $asset = $this->asset((int) ($data['businessAccountId'] ?? 0));
        $template = $this->templateManager->create($asset, (string) ($data['name'] ?? ''), (string) ($data['language'] ?? ''), (string) ($data['category'] ?? ''), $this->components($data));

        return ['status' => 'created', 'template' => $this->normalizeTemplate($template)];
    }

    private function updateTemplate(?int $id, array $data): array
    {
        $this->assertPermission('edit', 'templates');
        $template = $this->template($id);
        $this->templateManager->update($template, (string) ($data['category'] ?? $template->getCategory()), $this->components($data));

        return ['status' => 'updated', 'template' => $this->normalizeTemplate($template)];
    }

    private function deleteTemplate(?int $id, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Deleting a Meta template requires confirm=true.');
        }

        $this->assertPermission('delete', 'templates');
        $template = $this->template($id);
        $deletedId = $template->getId();
        $this->templateManager->delete($template);

        return ['status' => 'deleted', 'successIds' => [$deletedId], 'failureIds' => []];
    }

    private function syncTemplates(?int $id): array
    {
        $this->assertPermission('edit', 'templates');

        return ['status' => 'synchronized', 'businessAccountId' => $id, 'result' => $this->templateManager->synchronize($this->asset((int) $id))];
    }

    private function setConsent(?int $id, array $data): array
    {
        $this->assertPermission('edit', 'messages');
        $identity = $this->identity($id);
        $status = ConsentStatus::tryFrom((string) ($data['status'] ?? ''));
        if (null === $status) {
            throw new BadRequestHttpException('status must be unknown, opted_in, or opted_out.');
        }

        $this->identityManager->changeConsent($identity, $status, 'mcp');

        return ['status' => 'updated', 'identity' => $this->normalizeIdentity($identity)];
    }

    private function linkIdentity(?int $id, array $data): array
    {
        $this->assertPermission('edit', 'messages');
        $identity = $this->identity($id);
        $contact = $this->contact(isset($data['contactId']) ? (int) $data['contactId'] : null);
        $this->identityManager->associate($identity, $contact);

        return ['status' => 'updated', 'identity' => $this->normalizeIdentity($identity)];
    }

    private function testConnection(?int $id, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Testing an external Meta connection requires confirm=true.');
        }

        $this->assertPermission('edit', 'connections');

        return $this->diagnostic->test($this->connection($id));
    }

    private function createConnection(array $data): array
    {
        $this->assertPermission('create', 'connections');
        $connection = $this->connectionManager->create((string) ($data['name'] ?? ''), (string) ($data['app_id'] ?? ''), (string) ($data['app_secret'] ?? ''), (string) ($data['access_token'] ?? ''), (string) ($data['verify_token'] ?? ''), (string) ($data['graph_version'] ?? 'v26.0'), (string) ($data['webhook_adapters_json'] ?? ''));

        return ['status' => 'created', 'connection' => $this->normalizeConnection($connection)];
    }

    private function updateConnection(?int $id, array $data): array
    {
        $this->assertPermission('edit', 'connections');
        $connection = $this->connection($id);
        $merged = $data + ['name' => $connection->getName(), 'app_id' => $connection->getAppId(), 'graph_version' => $connection->getGraphVersion()];
        $this->connectionManager->update($connection, $merged);

        return ['status' => 'updated', 'connection' => $this->normalizeConnection($connection)];
    }

    private function deleteConnection(?int $id, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Deleting a Meta connection requires confirm=true.');
        }

        $this->assertPermission('delete', 'connections');
        $connection = $this->connection($id);
        $deletedId = $connection->getId();
        $this->connectionManager->remove($connection);

        return ['status' => 'deleted', 'successIds' => [$deletedId], 'failureIds' => []];
    }

    private function createAsset(?int $id, array $data): array
    {
        $this->assertPermission('edit', 'connections');
        $asset = $this->assetManager->create($this->connection($id), $data);

        return ['status' => 'created', 'asset' => $this->normalizeAsset($asset)];
    }

    private function updateAsset(?int $id, array $data): array
    {
        $this->assertPermission('edit', 'connections');
        $asset = $this->asset((int) $id);
        $settings = $asset->getSettings();
        $merged = $data + ['name' => $asset->getName(), 'type' => $asset->getType()->value, 'external_id' => $asset->getExternalId(), 'username' => $asset->getUsername(), 'phone_number' => $asset->getPhoneNumber(), 'default_region' => $settings['default_region'] ?? 'BR', 'contact_match_field' => $settings['contact_match_field'] ?? null, 'require_opt_in' => $settings['require_opt_in'] ?? true, 'daily_send_limit' => $settings['daily_send_limit'] ?? null, 'hourly_send_limit' => $settings['hourly_send_limit'] ?? null, 'recipient_daily_limit' => $settings['recipient_daily_limit'] ?? null, 'recipient_cooldown_seconds' => $settings['recipient_cooldown_seconds'] ?? null, 'is_default' => $asset->isDefault()];
        $this->assetManager->update($asset, $merged);

        return ['status' => 'updated', 'asset' => $this->normalizeAsset($asset)];
    }

    private function deleteAsset(?int $id, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Deleting a Meta asset requires confirm=true.');
        }

        $this->assertPermission('delete', 'connections');
        $asset = $this->asset((int) $id);
        $deletedId = $asset->getId();
        $this->assetManager->remove($asset);

        return ['status' => 'deleted', 'successIds' => [$deletedId], 'failureIds' => []];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function components(array $data): array
    {
        $components = $data['components'] ?? null;
        if (!is_array($components) || !array_is_list($components)) {
            throw new BadRequestHttpException('data.components must be an array.');
        }

        return $components;
    }

    private function connection(?int $id): MetaConnection
    {
        $entity = null === $id ? null : $this->connections->find($id);
        if (!$entity instanceof MetaConnection) {
            throw new NotFoundHttpException('Meta connection not found.');
        }

        return $entity;
    }

    private function asset(int $id): MetaAsset
    {
        $entity = $this->assets->find($id);
        if (!$entity instanceof MetaAsset) {
            throw new NotFoundHttpException('Meta asset not found.');
        }

        return $entity;
    }

    private function template(?int $id): WhatsAppTemplate
    {
        $entity = null === $id ? null : $this->templates->find($id);
        if (!$entity instanceof WhatsAppTemplate) {
            throw new NotFoundHttpException('WhatsApp template not found.');
        }

        return $entity;
    }

    private function identity(?int $id): MetaContactIdentity
    {
        $entity = null === $id ? null : $this->identities->find($id);
        if (!$entity instanceof MetaContactIdentity) {
            throw new NotFoundHttpException('Meta identity not found.');
        }

        return $entity;
    }

    private function contact(?int $id): ?Lead
    {
        if (null === $id || 0 === $id) {
            return null;
        }

        $lead = $this->leads->getEntity($id);
        if (!$lead instanceof Lead) {
            throw new NotFoundHttpException('Mautic contact not found.');
        }

        return $lead;
    }

    private function requiredResourceId(?string $id, string $resource): string
    {
        $id = trim((string) $id);
        if ('' === $id) {
            throw new BadRequestHttpException($resource.' resourceId is required.');
        }

        return $id;
    }

    private function assertPermission(string $level, string $resource): void
    {
        $permission = 'meta:'.$resource.':'.$level;
        if (!$this->permissions->checkPermissionExists($permission) || !$this->permissions->isGranted($permission)) {
            throw new AccessDeniedException('Permission denied for '.$permission.'.');
        }
    }

    private function normalizeConnection(MetaConnection $item): array
    {
        $settings = $item->getSettings();
        if (is_array($settings['webhook_adapters'] ?? null)) {
            $settings['webhook_adapters'] = array_map(static function (array $adapter): array {
                unset($adapter['sealed_secret']);
                $adapter['secretConfigured'] = true;

                return $adapter;
            }, $settings['webhook_adapters']);
        }

        return [
            'id'             => $item->getId(),
            'name'           => $item->getName(),
            'appId'          => $item->getAppId(),
            'status'         => $item->getStatus(),
            'graphVersion'   => $item->getGraphVersion(),
            'tokenExpiresAt' => $item->getTokenExpiresAt()?->format(DATE_ATOM),
            'settings'       => $settings,
        ];
    }

    private function normalizeAsset(MetaAsset $item): array
    {
        return ['id' => $item->getId(), 'connectionId' => $item->getConnection()->getId(), 'externalId' => $item->getExternalId(), 'type' => $item->getType()->value, 'name' => $item->getName(), 'username' => $item->getUsername(), 'phoneNumber' => $item->getPhoneNumber(), 'status' => $item->getStatus(), 'published' => $item->isPublished(), 'settings' => $item->getSettings()];
    }

    private function normalizeTemplate(WhatsAppTemplate $item): array
    {
        return ['id' => $item->getId(), 'businessAccountId' => $item->getBusinessAccount()->getId(), 'externalId' => $item->getExternalId(), 'name' => $item->getName(), 'language' => $item->getLanguage(), 'category' => $item->getCategory(), 'status' => $item->getStatus(), 'qualityScore' => $item->getQualityScore(), 'components' => $item->getComponents(), 'lastSyncedAt' => $item->getLastSyncedAt()->format(DATE_ATOM)];
    }

    private function normalizeIdentity(MetaContactIdentity $item): array
    {
        return ['id' => $item->getId(), 'assetId' => $item->getAsset()->getId(), 'contactId' => $item->getContact()?->getId(), 'externalId' => $item->getExternalId(), 'username' => $item->getUsername(), 'phoneNumber' => $item->getPhoneNumber(), 'consentStatus' => $item->getConsentStatus()->value, 'consentSource' => $item->getConsentSource(), 'consentedAt' => $item->getConsentedAt()?->format(DATE_ATOM), 'optedOutAt' => $item->getOptedOutAt()?->format(DATE_ATOM), 'lastInteractionAt' => $item->getLastInteractionAt()?->format(DATE_ATOM)];
    }

    private function normalizeMessage(MetaMessage $item): array
    {
        return ['id' => $item->getId(), 'assetId' => $item->getAsset()->getId(), 'contactId' => $item->getContact()?->getId(), 'externalId' => $item->getExternalId(), 'channel' => $item->getChannel(), 'direction' => $item->getDirection(), 'messageType' => $item->getMessageType(), 'recipient' => $item->getRecipient(), 'status' => $item->getStatus(), 'error' => $item->getError(), 'dateAdded' => $item->getDateAdded()->format(DATE_ATOM)];
    }

    private function normalizeJob(MetaOutboundJob $item): array
    {
        return ['id' => $item->getId(), 'assetId' => $item->getAsset()->getId(), 'contactId' => $item->getContact()?->getId(), 'operation' => $item->getOperation(), 'status' => $item->getStatus(), 'attempts' => $item->getAttempts(), 'maxAttempts' => $item->getMaxAttempts(), 'availableAt' => $item->getAvailableAt()?->format(DATE_ATOM), 'completedAt' => $item->getCompletedAt()?->format(DATE_ATOM), 'lastError' => $item->getLastError(), 'messageLogId' => $item->getMessageLogId()];
    }
}
