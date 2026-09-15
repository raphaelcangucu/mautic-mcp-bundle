<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Mcp;

use MauticPlugin\MauticMcpBundle\Mcp\Tool\Management\ReadSegmentsTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use PHPUnit\Framework\TestCase;

final class SegmentToolContractTest extends TestCase
{
    public function testReadSegmentsPublishesMembersAction(): void
    {
        $classAttributes = (new \ReflectionClass(ReadSegmentsTool::class))->getAttributes(McpTool::class);
        self::assertCount(1, $classAttributes);
        self::assertSame('mautic_read_segments', $classAttributes[0]->getArguments()['name']);

        $method = new \ReflectionMethod(ReadSegmentsTool::class, '__invoke');
        $parameters = $method->getParameters();
        $actionSchema = $parameters[0]->getAttributes(Schema::class)[0]->getArguments();

        self::assertSame(['list', 'get', 'members'], $actionSchema['enum']);
        self::assertSame('metaOptedIn', $parameters[5]->getName());
        self::assertTrue($parameters[5]->allowsNull());
        self::assertNull($parameters[5]->getDefaultValue());
        self::assertStringContainsString('Omit/null for all members', $parameters[5]->getAttributes(Schema::class)[0]->getArguments()['description']);
    }
}
