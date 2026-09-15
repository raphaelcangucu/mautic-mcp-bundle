<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Campaign;

use MauticPlugin\MauticMcpBundle\Application\Management\MauticManagementService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MetaCampaignCreationService
{
    public const INSTAGRAM_COMMENT = 'create_instagram_comment';
    public const WHATSAPP_TEMPLATE = 'create_whatsapp_template';

    public function __construct(
        private MauticManagementService $management,
        private CampaignFlowService $flows,
    ) {
    }

    public function preview(string $action, array $data, array $segmentIds): array
    {
        [$campaign, $events] = $this->definition($action, $data);
        $validation = $this->flows->validateFlow($events);

        return [
            'resource' => 'campaign',
            'action' => $action,
            'campaign' => $campaign,
            'segmentIds' => $this->segmentIds($segmentIds),
            'flow' => $validation,
        ];
    }

    public function create(string $action, array $data, array $segmentIds, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Creating a Meta campaign with its flow requires confirm=true.');
        }

        $preview = $this->preview($action, $data, $segmentIds);
        $created = $this->management->campaigns(
            'create',
            null,
            $preview['campaign'],
            [],
            $preview['segmentIds'],
            '',
            20,
            1,
            false,
        );
        $campaignId = (int) ($created['campaign']['id'] ?? 0);
        if ($campaignId < 1) {
            throw new \RuntimeException('Mautic created the campaign without returning its ID.');
        }

        try {
            $flow = $this->flows->replaceFlow($campaignId, $preview['flow']['events'], true);
        } catch (\Throwable $exception) {
            try {
                $this->management->campaigns('delete', $campaignId, [], [], [], '', 20, 1, true);
            } catch (\Throwable) {
                throw new \RuntimeException(
                    sprintf('Campaign %d was created but its Meta flow failed: %s', $campaignId, $exception->getMessage()),
                    previous: $exception,
                );
            }

            throw $exception;
        }

        $updated = $this->management->campaigns('get', $campaignId, [], [], [], '', 20, 1, false);

        return [
            'status' => 'created_with_flow',
            'campaign' => $updated['campaign'],
            'flow' => $flow,
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function definition(string $action, array $data): array
    {
        $campaign = array_intersect_key($data, array_flip([
            'name',
            'description',
            'allowRestart',
            'publishUp',
            'publishDown',
        ]));
        $campaign['name'] = trim((string) ($campaign['name'] ?? ''));
        if ('' === $campaign['name']) {
            throw new BadRequestHttpException('Campaign data.name is required.');
        }

        // Meta campaign creation always starts as a reviewable draft.
        $campaign['isPublished'] = false;

        return match ($action) {
            self::INSTAGRAM_COMMENT => [
                $campaign + ['allowRestart' => true],
                $this->instagramCommentEvents($data['instagramComment'] ?? null),
            ],
            self::WHATSAPP_TEMPLATE => [
                $campaign + ['allowRestart' => false],
                $this->whatsAppTemplateEvents($data['whatsapp'] ?? null),
            ],
            default => throw new BadRequestHttpException('Unsupported Meta campaign creation action.'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function instagramCommentEvents(mixed $settings): array
    {
        if (!is_array($settings)) {
            throw new BadRequestHttpException('data.instagramComment must be an object.');
        }

        return [
            [
                'key' => 'instagram_comment',
                'name' => (string) ($settings['decisionName'] ?? 'Comentário do Instagram corresponde'),
                'type' => 'meta.instagram.comment',
                'eventType' => 'decision',
                'parent' => null,
                'properties' => [
                    'asset_id' => (int) ($settings['assetId'] ?? 0),
                    'media_id' => trim((string) ($settings['mediaId'] ?? '')),
                    'keyword' => trim((string) ($settings['keyword'] ?? 'relatorio')),
                ],
                'position' => ['x' => 400, 'y' => 160],
            ],
            [
                'key' => 'private_reply',
                'name' => (string) ($settings['replyName'] ?? 'Enviar relatório por mensagem privada'),
                'type' => 'meta.instagram.comment.private_reply',
                'eventType' => 'action',
                'parent' => 'instagram_comment',
                'path' => 'yes',
                'properties' => [
                    'message' => trim((string) ($settings['privateReply'] ?? '')),
                ],
                'position' => ['x' => 400, 'y' => 360],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function whatsAppTemplateEvents(mixed $settings): array
    {
        if (!is_array($settings)) {
            throw new BadRequestHttpException('data.whatsapp must be an object.');
        }
        $bodyParameters = $settings['bodyParameters'] ?? '';
        if (is_array($bodyParameters)) {
            $bodyParameters = implode("\n", array_map(static fn (mixed $value): string => (string) $value, $bodyParameters));
        }
        if (!is_string($bodyParameters)) {
            throw new BadRequestHttpException('data.whatsapp.bodyParameters must be a string or an array of strings.');
        }

        return [[
            'key' => 'whatsapp_template',
            'name' => (string) ($settings['actionName'] ?? 'Enviar template do WhatsApp'),
            'type' => 'meta.whatsapp.send',
            'eventType' => 'action',
            'parent' => null,
            'properties' => [
                'asset_id' => (int) ($settings['assetId'] ?? 0),
                'mode' => 'template',
                'phone_field' => trim((string) ($settings['phoneField'] ?? 'mobile')),
                'template_name' => trim((string) ($settings['templateName'] ?? '')),
                'language' => trim((string) ($settings['language'] ?? 'pt_BR')),
                'body_parameters' => $bodyParameters,
                'message' => '',
                'queue' => (bool) ($settings['queue'] ?? true),
                'max_attempts' => (int) ($settings['maxAttempts'] ?? 5),
            ],
            'position' => ['x' => 400, 'y' => 160],
        ]];
    }

    /**
     * @return list<int>
     */
    private function segmentIds(array $segmentIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $segmentIds)));
        if ([] !== array_filter($ids, static fn (int $id): bool => $id < 1)) {
            throw new BadRequestHttpException('segmentIds must contain positive IDs only.');
        }

        return $ids;
    }
}
