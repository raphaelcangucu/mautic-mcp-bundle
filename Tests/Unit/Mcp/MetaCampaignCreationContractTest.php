<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Mcp;

use MauticPlugin\MauticMcpBundle\Application\Campaign\CampaignFlowService;
use MauticPlugin\MauticMcpBundle\Application\Campaign\MetaCampaignCreationService;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Management\ManageCampaignsTool;
use Mcp\Capability\Attribute\Schema;
use PHPUnit\Framework\TestCase;

final class MetaCampaignCreationContractTest extends TestCase
{
    public function testCampaignToolPublishesMetaCreationActionsAndInputs(): void
    {
        $method = new \ReflectionMethod(ManageCampaignsTool::class, '__invoke');
        $parameters = $method->getParameters();
        $actionSchema = $parameters[0]->getAttributes(Schema::class)[0]->getArguments();
        $dataSchema = $parameters[2]->getAttributes(Schema::class)[0]->getArguments();

        self::assertContains(MetaCampaignCreationService::INSTAGRAM_COMMENT, $actionSchema['enum']);
        self::assertContains(MetaCampaignCreationService::WHATSAPP_TEMPLATE, $actionSchema['enum']);
        self::assertStringContainsString('instagramComment', $dataSchema['description']);
        self::assertStringContainsString('whatsapp', $dataSchema['description']);
        self::assertStringContainsString('templateName', $dataSchema['description']);
    }

    public function testInstagramCommentPresetBuildsDraftDecisionAndPrivateReply(): void
    {
        [$campaign, $events] = $this->definition(MetaCampaignCreationService::INSTAGRAM_COMMENT, [
            'name' => 'Instagram report',
            'allowRestart' => false,
            'isPublished' => true,
            'instagramComment' => [
                'assetId' => 4,
                'mediaId' => '1234567890',
                'keyword' => 'relatorio',
                'privateReply' => 'Aqui está o relatório.',
            ],
        ]);

        self::assertFalse($campaign['isPublished']);
        self::assertFalse($campaign['allowRestart']);
        self::assertSame('meta.instagram.comment', $events[0]['type']);
        self::assertSame(['asset_id' => 4, 'media_id' => '1234567890', 'keyword' => 'relatorio'], $events[0]['properties']);
        self::assertSame('meta.instagram.comment.private_reply', $events[1]['type']);
        self::assertSame('instagram_comment', $events[1]['parent']);
        self::assertSame('yes', $events[1]['path']);
        self::assertSame('Aqui está o relatório.', $events[1]['properties']['message']);
    }

    public function testWhatsAppPresetBuildsDraftApprovedTemplateAction(): void
    {
        [$campaign, $events] = $this->definition(MetaCampaignCreationService::WHATSAPP_TEMPLATE, [
            'name' => 'WhatsApp report',
            'whatsapp' => [
                'assetId' => 13,
                'templateName' => 'relatorio_inteligencia_disponivel',
                'language' => 'pt_BR',
                'phoneField' => 'mobile',
                'bodyParameters' => ['Rodada 28', 'https://example.test/report'],
                'queue' => true,
                'maxAttempts' => 3,
            ],
        ]);

        self::assertFalse($campaign['isPublished']);
        self::assertFalse($campaign['allowRestart']);
        self::assertSame('meta.whatsapp.send', $events[0]['type']);
        self::assertSame('template', $events[0]['properties']['mode']);
        self::assertSame(13, $events[0]['properties']['asset_id']);
        self::assertSame("Rodada 28\nhttps://example.test/report", $events[0]['properties']['body_parameters']);
        self::assertSame(3, $events[0]['properties']['max_attempts']);
    }

    public function testFlowCatalogPublishesChannelPropertySchemas(): void
    {
        $reflection = new \ReflectionClass(CampaignFlowService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('knownPropertiesSchema');
        $method->setAccessible(true);

        $instagram = $method->invoke($service, 'meta.instagram.comment');
        $whatsApp = $method->invoke($service, 'meta.whatsapp.send');

        self::assertSame(['asset_id', 'media_id', 'keyword'], $instagram['required']);
        self::assertSame(['template', 'text'], $whatsApp['properties']['mode']['enum']);
        self::assertSame(10, $whatsApp['properties']['max_attempts']['maximum']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function definition(string $action, array $data): array
    {
        $reflection = new \ReflectionClass(MetaCampaignCreationService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('definition');
        $method->setAccessible(true);

        return $method->invoke($service, $action, $data);
    }
}
