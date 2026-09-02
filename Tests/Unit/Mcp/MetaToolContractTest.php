<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Mcp;

use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\CancelWhatsAppConsentSyncTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\GetWhatsAppConsentSyncStatusTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\ListWhatsAppConsentSyncRejectionsTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\ManageMetaTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\MetaSetupTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\PreviewWhatsAppConsentSyncTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\ReadMetaApiTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\ReadMetaTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\RegisterLandingWhatsAppOptInTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\SendMetaMessageTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta\StartWhatsAppConsentSyncTool;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetaToolContractTest extends TestCase
{
    #[DataProvider('tools')]
    public function testMetaToolPublishesMcpContract(string $class, string $expectedName): void
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(McpTool::class);

        self::assertCount(1, $attributes);
        $arguments = $attributes[0]->getArguments();
        self::assertSame($expectedName, $arguments['name']);
        self::assertArrayHasKey('annotations', $arguments);
        self::assertArrayHasKey('outputSchema', $arguments);
    }

    public static function tools(): iterable
    {
        yield [ReadMetaTool::class, 'mautic_read_meta'];
        yield [MetaSetupTool::class, 'mautic_meta_setup'];
        yield [ReadMetaApiTool::class, 'mautic_read_meta_api'];
        yield [SendMetaMessageTool::class, 'mautic_send_meta_message'];
        yield [ManageMetaTool::class, 'mautic_manage_meta'];
        yield [RegisterLandingWhatsAppOptInTool::class, 'register_landing_whatsapp_opt_in'];
        yield [PreviewWhatsAppConsentSyncTool::class, 'preview_whatsapp_consent_sync'];
        yield [StartWhatsAppConsentSyncTool::class, 'start_whatsapp_consent_sync'];
        yield [GetWhatsAppConsentSyncStatusTool::class, 'get_whatsapp_consent_sync_status'];
        yield [ListWhatsAppConsentSyncRejectionsTool::class, 'list_whatsapp_consent_sync_rejections'];
        yield [CancelWhatsAppConsentSyncTool::class, 'cancel_whatsapp_consent_sync'];
    }
}
