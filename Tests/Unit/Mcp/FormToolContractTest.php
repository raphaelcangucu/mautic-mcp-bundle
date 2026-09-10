<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Mcp;

use MauticPlugin\MauticMcpBundle\Mcp\Tool\Form\ManageFormsTool;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\Form\ReadFormsTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Capability\Discovery\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class FormToolContractTest extends TestCase
{
    public function testManageFormsPublishesSafeMutationContract(): void
    {
        $attributes = (new \ReflectionClass(ManageFormsTool::class))->getAttributes(McpTool::class);
        self::assertCount(1, $attributes);
        self::assertSame('mautic_manage_forms', $attributes[0]->getArguments()['name']);

        $method = new \ReflectionMethod(ManageFormsTool::class, '__invoke');
        $parameters = $method->getParameters();
        $inputSchema = $method->getAttributes(Schema::class)[0]->newInstance()->definition;
        self::assertIsArray($inputSchema);
        self::assertSame(['create', 'update', 'delete', 'publish', 'unpublish'], $inputSchema['properties']['action']['enum']);

        $dataSchema = $inputSchema['properties']['data']['anyOf'][0];
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

    public function testSchemaAcceptsEmptyJsonObjectsDecodedByTheSdk(): void
    {
        $method = new \ReflectionMethod(ManageFormsTool::class, '__invoke');
        $inputSchema = $method->getAttributes(Schema::class)[0]->newInstance()->definition;
        $arguments = json_decode(<<<'JSON'
{"action":"create","data":{"name":"MCP form","fields":[{"label":"Message","type":"textarea","properties":{},"validation":{},"conditions":{}}],"actions":[{"type":"lead.changetags","properties":{}}]},"dryRun":true}
JSON, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([], (new SchemaValidator())->validateAgainstJsonSchema($arguments, $inputSchema));
    }

    public function testSchemaAcceptsExplicitlyEmptyDataObject(): void
    {
        $method = new \ReflectionMethod(ManageFormsTool::class, '__invoke');
        $inputSchema = $method->getAttributes(Schema::class)[0]->newInstance()->definition;
        $arguments = json_decode('{"action":"delete","id":42,"data":{},"confirm":true}', true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([], (new SchemaValidator())->validateAgainstJsonSchema($arguments, $inputSchema));
    }

    public function testReadFormsRemainsTheDedicatedReadTool(): void
    {
        $attributes = (new \ReflectionClass(ReadFormsTool::class))->getAttributes(McpTool::class);

        self::assertCount(1, $attributes);
        self::assertSame('mautic_read_forms', $attributes[0]->getArguments()['name']);
    }
}
