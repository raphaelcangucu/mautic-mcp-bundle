<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Mcp;

use MauticPlugin\MauticMcpBundle\Mcp\Tool\Form\ManageFormsTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Form\ReadFormsTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use PHPUnit\Framework\TestCase;

final class FormToolContractTest extends TestCase
{
    public function testManageFormsPublishesSafeMutationContract(): void
    {
        $attributes = (new \ReflectionClass(ManageFormsTool::class))->getAttributes(McpTool::class);
        self::assertCount(1, $attributes);
        self::assertSame('mautic_manage_forms', $attributes[0]->getArguments()['name']);

        $parameters = (new \ReflectionMethod(ManageFormsTool::class, '__invoke'))->getParameters();
        $actionSchema = $parameters[0]->getAttributes(Schema::class)[0]->getArguments();
        self::assertSame(['create', 'update', 'delete', 'publish', 'unpublish'], $actionSchema['enum']);

        $dataSchema = $parameters[2]->getAttributes(Schema::class)[0]->getArguments();
        foreach (['fields', 'actions', 'deleteFieldIds', 'deleteActionIds'] as $property) {
            self::assertArrayHasKey($property, $dataSchema['properties']);
        }
        self::assertFalse($dataSchema['additionalProperties']);
        self::assertFalse($dataSchema['properties']['fields']['items']['additionalProperties']);
        self::assertFalse($dataSchema['properties']['actions']['items']['additionalProperties']);
        self::assertSame('confirm', $parameters[3]->getName());
        self::assertSame('dryRun', $parameters[4]->getName());
        self::assertSame('idempotencyKey', $parameters[5]->getName());
        self::assertSame('expectedDateModified', $parameters[6]->getName());
    }

    public function testReadFormsRemainsTheDedicatedReadTool(): void
    {
        $attributes = (new \ReflectionClass(ReadFormsTool::class))->getAttributes(McpTool::class);

        self::assertCount(1, $attributes);
        self::assertSame('mautic_read_forms', $attributes[0]->getArguments()['name']);
    }
}
