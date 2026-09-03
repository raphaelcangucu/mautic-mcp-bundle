<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Meta;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticMetaBundle\Domain\AssetType;
use MauticPlugin\MauticMetaBundle\Entity\MetaAsset;
use MauticPlugin\MauticMetaBundle\Entity\MetaAssetRepository;
use MauticPlugin\MauticMetaBundle\Entity\MetaConnection;
use MauticPlugin\MauticMetaBundle\Entity\MetaConnectionRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MetaSetupService
{
    public function __construct(
        private CorePermissions $permissions,
        private MetaConnectionRepository $connections,
        private MetaAssetRepository $assets,
        private UrlGeneratorInterface $urls
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function guide(string $section = 'all', ?int $connectionId = null): array
    {
        $this->assertViewPermission();
        $available = ['all', 'status', 'installation', 'meta_app', 'connections', 'assets', 'webhooks', 'campaigns', 'queue', 'permissions', 'mcp', 'troubleshooting'];
        if (!in_array($section, $available, true)) {
            throw new \InvalidArgumentException('Unsupported setup section.');
        }

        $sections = $this->sections($connectionId);

        return [
            'plugin' => ['name' => 'Mautic Meta Bundle', 'version' => '0.10.3', 'package' => 'raphaelcangucu/mautic-meta-bundle'],
            'section' => $section,
            'availableSections' => $available,
            'status' => $this->status(),
            'guide' => 'all' === $section ? $sections : [$section => $sections[$section]],
            'important' => [
                'Never paste access tokens or app secrets into prompts, logs, source control, or support tickets.',
                'Use a Meta System User token in production and rotate credentials from the Mautic connection screen or mautic_manage_meta.',
                'Keep queued delivery enabled and verify consent/DNC rules before production sends.',
                'Meta permissions and App Review requirements can change; confirm them in the linked official documentation before submitting the app.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function status(): array
    {
        $connections = $this->connections->findBy([], ['name' => 'ASC']);
        $assets = $this->assets->findBy([], ['id' => 'ASC']);
        $activeConnections = array_filter($connections, static fn (MetaConnection $connection): bool => 'active' === $connection->getStatus() && $connection->isPublished());
        $activeAssets = array_filter($assets, static fn (MetaAsset $asset): bool => 'active' === $asset->getStatus() && $asset->isPublished() && 'active' === $asset->getConnection()->getStatus() && $asset->getConnection()->isPublished());
        $assetCounts = array_fill_keys(array_map(static fn (AssetType $type): string => $type->value, AssetType::cases()), 0);
        foreach ($assets as $asset) {
            ++$assetCounts[$asset->getType()->value];
        }
        $issues = [];
        if ([] === $connections) {
            $issues[] = 'No Meta connection exists. Create one before adding assets.';
        } elseif ([] === $activeConnections) {
            $issues[] = 'No connection is active. Run test_connection after saving valid credentials.';
        }
        if (0 === $assetCounts[AssetType::WhatsAppPhoneNumber->value] && 0 === $assetCounts[AssetType::InstagramAccount->value]) {
            $issues[] = 'No sending asset exists. Add a WhatsApp phone number or Instagram professional account.';
        }

        return [
            'configured' => [] !== $connections && [] !== $activeConnections && [] !== $activeAssets,
            'connectionCount' => count($connections),
            'activeConnectionCount' => count($activeConnections),
            'assetCount' => count($assets),
            'activeAssetCount' => count($activeAssets),
            'assetCountsByType' => $assetCounts,
            'issues' => $issues,
            'connections' => array_map(fn (MetaConnection $connection): array => [
                'id' => $connection->getId(), 'name' => $connection->getName(), 'status' => $connection->getStatus(),
                'graphVersion' => $connection->getGraphVersion(), 'published' => $connection->isPublished(),
                'webhookUrl' => $this->webhookUrl((int) $connection->getId()),
                'lastDiagnostic' => $connection->getSettings()['last_diagnostic'] ?? null,
            ], $connections),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sections(?int $connectionId): array
    {
        return [
            'status' => ['purpose' => 'Inspect the current installation and follow status.issues in order.', 'nextAction' => 'Call mautic_meta_setup with the section named by the issue, then use dryRun before any write.'],
            'installation' => [
                'commands' => [
                    'composer config repositories.mautic-meta vcs https://github.com/raphaelcangucu/mautic-meta-bundle',
                    'composer require raphaelcangucu/mautic-meta-bundle:^0.5',
                    'php bin/console mautic:plugins:reload --env=prod',
                    'php bin/console doctrine:schema:update --force --env=prod',
                    'php bin/console cache:clear --env=prod',
                ],
                'legacyMigration' => ['dryRun' => 'php bin/console mautic:meta:migrate-whatsapp --app-id=YOUR_META_APP_ID --dry-run', 'execute' => 'php bin/console mautic:meta:migrate-whatsapp --app-id=YOUR_META_APP_ID'],
                'repository' => 'https://github.com/raphaelcangucu/mautic-meta-bundle',
            ],
            'meta_app' => [
                'steps' => [
                    'Create or select an app in Meta for Developers and connect the appropriate Business Portfolio.',
                    'Add WhatsApp and/or Instagram products and link the WABA, phone number, Facebook Page, and Instagram professional account.',
                    'Create a System User token for production; grant only the assets and permissions required by the enabled channels.',
                    'Complete App Review and Business Verification when Meta requires production access.',
                ],
                'typicalPermissions' => [
                    'whatsapp' => ['whatsapp_business_management', 'whatsapp_business_messaging', 'business_management'],
                    'instagram' => ['instagram_business_basic', 'instagram_business_manage_messages', 'instagram_business_manage_comments', 'instagram_business_manage_insights'],
                ],
                'officialDocs' => ['whatsapp' => 'https://developers.facebook.com/docs/whatsapp/cloud-api/', 'instagram' => 'https://developers.facebook.com/docs/instagram-platform/', 'webhooks' => 'https://developers.facebook.com/docs/graph-api/webhooks/'],
            ],
            'connections' => [
                'uiUrl' => $this->route('mautic_meta_connections'),
                'multipleAccounts' => true,
                'fields' => [
                    'name' => 'Internal label.', 'app_id' => 'Meta App ID.', 'app_secret' => 'Meta App Secret; encrypted at rest.',
                    'access_token' => 'Prefer a non-human System User token; encrypted at rest.', 'verify_token' => 'A random value you choose and also enter in Meta webhook configuration.',
                    'graph_version' => 'Version in vNN.N format. Review it during Meta API upgrades.',
                    'webhook_adapters_json' => 'Optional JSON array of signed omnichannel webhook destinations. Adapter names must be unique.',
                ],
                'mcp' => [
                    'create' => ['tool' => 'mautic_manage_meta', 'action' => 'create_connection', 'dataRequired' => ['name', 'app_id', 'app_secret', 'access_token', 'verify_token'], 'supports' => ['dryRun', 'idempotencyKey']],
                    'update' => ['tool' => 'mautic_manage_meta', 'action' => 'update_connection', 'id' => 'connection ID', 'note' => 'Omit secret fields to preserve their encrypted current values.'],
                    'test' => ['tool' => 'mautic_manage_meta', 'action' => 'test_connection', 'id' => 'connection ID', 'confirm' => true],
                    'read' => ['tool' => 'mautic_read_meta', 'resource' => 'connections'],
                ],
            ],
            'omnichannelAdapters' => [
                'configurationField' => 'webhook_adapters_json',
                'multipleDestinations' => true,
                'fields' => ['name', 'url', 'secret', 'enabled', 'allowReplies', 'events', 'channels', 'timeout', 'maxAttempts'],
                'events' => ['message.received', 'message.sent', 'message.delivered', 'message.read', 'message.failed'],
                'channels' => ['whatsapp', 'instagram'],
                'replyUrl' => '/meta/adapters/{connectionId}/{urlEncodedAdapterName}/messages',
                'replyBody' => ['conversationId' => 'integer', 'text' => 'non-empty string', 'idempotencyKey' => 'unique string'],
                'signature' => 'sha256=HMAC_SHA256(timestamp + "." + rawBody, secret)',
                'headers' => ['X-Mautic-Meta-Timestamp', 'X-Mautic-Meta-Signature'],
                'note' => 'Use secret="***" on updates to preserve the encrypted secret already stored in Mautic.',
            ],
            'assets' => [
                'types' => [
                    'whatsapp_business_account' => 'WABA ID; required for template synchronization and management.',
                    'whatsapp_phone_number' => 'Phone Number ID; required for WhatsApp delivery and status callbacks.',
                    'instagram_account' => 'Instagram professional account ID; required for replies, DMs, reads, and insights.',
                    'facebook_page' => 'Facebook Page ID linked to Instagram where the selected Meta API flow requires it.',
                ],
                'settings' => ['default_region' => 'Fallback region for local WhatsApp numbers, e.g. BR.', 'require_opt_in' => 'Keep true unless lawful consent is enforced externally.', 'contact_match_field' => 'Optional exact Mautic field alias for inbound identity matching.', 'is_default' => 'Default asset for its type within the connection.'],
                'mcp' => ['create' => ['tool' => 'mautic_manage_meta', 'action' => 'create_asset', 'id' => 'parent connection ID'], 'read' => ['tool' => 'mautic_read_meta', 'resource' => 'assets']],
                'recommendedOrder' => ['Create the connection.', 'Add the WABA and WhatsApp phone number and/or Instagram professional account.', 'Run test_connection.', 'Synchronize WhatsApp templates.', 'Perform a dry-run send and then an approved real test.'],
            ],
            'webhooks' => [
                'callbackUrl' => null === $connectionId ? 'Choose a connectionId or read status.connections[].webhookUrl.' : $this->webhookUrlForExistingConnection($connectionId),
                'verifyToken' => 'Use the same verify_token stored on that Mautic connection.',
                'subscriptions' => ['whatsapp' => ['messages'], 'instagram' => ['comments', 'messages', 'messaging_postbacks']],
                'security' => ['GET challenge validates verify_token.', 'POST payloads require X-Hub-Signature-256 signed with the connection App Secret.', 'Each connection has its own callback URL and credentials.'],
                'testChecklist' => ['Meta webhook verification succeeds.', 'A real inbound event appears in Meta operations.', 'Failed events can be replayed after fixing configuration.', 'Delivery status updates the outbound message log.'],
            ],
            'campaigns' => [
                'actions' => ['meta.whatsapp.send' => 'Text or approved WhatsApp template; queue enabled by default.', 'meta.instagram.send' => 'Private comment reply, public reply, or direct message.'],
                'decision' => ['type' => 'Meta message received or updated', 'filters' => ['channel', 'direction', 'status', 'message_type', 'inbound text pattern']],
                'contactFields' => ['WhatsApp action reads a configured phone field, normally mobile.', 'Instagram action reads a field containing a comment ID or Instagram user ID.'],
                'safety' => ['WhatsApp opt-in and DNC are checked before delivery.', 'Instagram DNC is checked before delivery.', 'Use approved WhatsApp templates outside the customer-service window.'],
            ],
            'queue' => [
                'cron' => '* * * * * cd /path/to/mautic && php bin/console mautic:meta:queue:process --limit=100 --env=prod --no-interaction',
                'behavior' => ['Database-backed durable jobs.', 'Per-connection rate limiting.', 'Exponential retry for transient failures.', 'Permanent validation, consent, and DNC failures are not retried.', 'Stalled jobs are recovered automatically.'],
                'operationsUrl' => $this->route('mautic_meta_operations'),
                'mcpRead' => ['tool' => 'mautic_read_meta', 'resource' => 'queue'],
            ],
            'permissions' => [
                'resources' => ['connections', 'messages', 'templates', 'webhooks', 'analytics'],
                'levels' => ['view', 'create', 'edit', 'delete', 'publish', 'full'],
                'currentUser' => $this->permissionStatus(),
                'recommendation' => 'Grant least privilege. Sending requires message creation; external tests and configuration changes require edit rights.',
            ],
            'mcp' => [
                'endpoint' => $this->route('mautic_mcp_http_endpoint'),
                'tools' => ['mautic_meta_setup', 'mautic_read_meta', 'mautic_read_meta_api', 'mautic_send_meta_message', 'mautic_manage_meta'],
                'workflow' => ['Call mautic_meta_setup section=status.', 'Read existing connections/assets.', 'Use dryRun for creation or changes.', 'Create/test connection and assets.', 'Read status again.', 'Use a dry-run message, then obtain user approval before real delivery.'],
            ],
            'troubleshooting' => [
                'connection_error' => ['Check token expiry, asset assignment, App Review permissions, graph_version, and System User access.', 'Run mautic_manage_meta test_connection with confirm=true.'],
                'webhook_401' => ['Confirm X-Hub-Signature-256 and the App Secret belong to the connection in the callback URL.'],
                'webhook_verification' => ['Confirm the exact callback URL and verify_token; do not use the App Secret as verify token.'],
                'whatsapp_not_sent' => ['Check phone asset active/published state, recipient normalization, opt-in/DNC, approved template status, and queue error.'],
                'instagram_not_sent' => ['Check professional account linkage, recipient/comment ID, messaging window, permissions, and queue error.'],
                'commands' => ['php bin/console mautic:meta:queue:process --limit=10 -vv', 'php bin/console doctrine:schema:update --dump-sql', 'php bin/console cache:clear --env=prod'],
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissionStatus(): array
    {
        $result = [];
        foreach (['connections', 'messages', 'templates', 'webhooks', 'analytics'] as $resource) {
            foreach (['view', 'create', 'edit', 'delete'] as $level) {
                $permission = 'meta:'.$resource.':'.$level;
                $result[$permission] = $this->permissions->checkPermissionExists($permission) && $this->permissions->isGranted($permission);
            }
        }

        return $result;
    }

    private function assertViewPermission(): void
    {
        if (!$this->permissions->isGranted('meta:connections:view')) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('Permission denied for meta:connections:view.');
        }
    }

    private function webhookUrl(int $connectionId): string
    {
        return $this->urls->generate('mautic_meta_webhook', ['connectionId' => $connectionId], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function webhookUrlForExistingConnection(int $connectionId): string
    {
        if (!$this->connections->find($connectionId) instanceof MetaConnection) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Meta connection not found.');
        }

        return $this->webhookUrl($connectionId);
    }

    private function route(string $name): string
    {
        try {
            return $this->urls->generate($name, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable) {
            return 'Route unavailable: '.$name;
        }
    }
}
