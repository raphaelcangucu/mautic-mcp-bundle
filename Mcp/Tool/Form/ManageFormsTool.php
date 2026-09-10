<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Form;

use MauticPlugin\MauticMcpBundle\Application\Form\FormWriteService;
use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(name: 'mautic_manage_forms', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
final class ManageFormsTool extends AbstractMcpTool
{
    private const OBJECT_MAP_SCHEMA = [
        'anyOf' => [
            ['type' => 'object', 'additionalProperties' => true],
            ['type' => 'array', 'maxItems' => 0],
        ],
    ];

    private const FIELD_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'label' => ['type' => 'string'],
            'showLabel' => ['type' => ['boolean', 'null']],
            'alias' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'defaultValue' => ['type' => ['string', 'null']],
            'isRequired' => ['type' => 'boolean'],
            'validationMessage' => ['type' => ['string', 'null']],
            'helpMessage' => ['type' => ['string', 'null']],
            'order' => ['type' => 'integer', 'minimum' => 1],
            'fieldOrder' => ['type' => 'integer', 'minimum' => 1],
            'properties' => self::OBJECT_MAP_SCHEMA,
            'validation' => self::OBJECT_MAP_SCHEMA,
            'conditions' => self::OBJECT_MAP_SCHEMA,
            'parent' => ['type' => ['string', 'integer', 'null']],
            'labelAttributes' => ['type' => ['string', 'null']],
            'inputAttributes' => ['type' => ['string', 'null']],
            'containerAttributes' => ['type' => ['string', 'null']],
            'leadField' => ['type' => ['string', 'null']],
            'saveResult' => ['type' => ['boolean', 'null']],
            'isAutoFill' => ['type' => ['boolean', 'null']],
            'isReadOnly' => ['type' => 'boolean'],
            'showWhenValueExists' => ['type' => ['boolean', 'null']],
            'showAfterXSubmissions' => ['type' => ['integer', 'null']],
            'alwaysDisplay' => ['type' => ['boolean', 'null']],
            'mappedObject' => ['type' => ['string', 'null']],
            'mappedField' => ['type' => ['string', 'null']],
            'fieldWidth' => ['type' => 'string'],
        ],
        'additionalProperties' => false,
    ];

    private const ACTION_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => ['string', 'null']],
            'description' => ['type' => ['string', 'null']],
            'type' => ['type' => 'string'],
            'order' => ['type' => 'integer', 'minimum' => 1],
            'actionOrder' => ['type' => 'integer', 'minimum' => 1],
            'properties' => self::OBJECT_MAP_SCHEMA,
        ],
        'additionalProperties' => false,
    ];

    private const DATA_OBJECT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'description' => ['type' => ['string', 'null']],
            'alias' => ['type' => 'string', 'description' => 'Optional on create and immutable after creation.'],
            'isPublished' => ['type' => 'boolean'],
            'publishUp' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            'publishDown' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            'postAction' => ['type' => 'string', 'enum' => ['return', 'message', 'redirect', 'hideform']],
            'postActionProperty' => ['type' => ['string', 'null']],
            'template' => ['type' => ['string', 'null']],
            'language' => ['type' => ['string', 'null']],
            'inKioskMode' => ['type' => 'boolean'],
            'renderStyle' => ['type' => 'boolean'],
            'noIndex' => ['type' => ['boolean', 'null']],
            'formAttributes' => ['type' => ['string', 'null']],
            'progressiveProfilingLimit' => ['type' => ['integer', 'null'], 'minimum' => 1],
            'submissionLimit' => ['type' => ['integer', 'null'], 'minimum' => 0],
            'submissionLimitMessage' => ['type' => ['string', 'null']],
            'categoryId' => ['type' => ['integer', 'null'], 'minimum' => 1],
            'fields' => ['type' => 'array', 'maxItems' => 200, 'items' => self::FIELD_SCHEMA],
            'actions' => ['type' => 'array', 'maxItems' => 100, 'items' => self::ACTION_SCHEMA],
            'deleteFieldIds' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'uniqueItems' => true],
            'deleteActionIds' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'uniqueItems' => true],
        ],
        'additionalProperties' => false,
    ];

    private const DATA_SCHEMA = [
        'anyOf' => [
            self::DATA_OBJECT_SCHEMA,
            ['type' => 'array', 'maxItems' => 0],
        ],
        'default' => [],
    ];

    private const INPUT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete', 'publish', 'unpublish']],
            'id' => ['type' => ['integer', 'null'], 'minimum' => 1],
            'data' => self::DATA_SCHEMA,
            'confirm' => ['type' => 'boolean', 'default' => false],
            'dryRun' => ['type' => 'boolean', 'default' => false],
            'idempotencyKey' => ['type' => ['string', 'null']],
            'expectedDateModified' => ['type' => ['string', 'null'], 'format' => 'date-time'],
        ],
        'required' => ['action'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private FormWriteService $service,
        private MutationExecutor $mutations,
    ) {
    }

    /**
     * Create/update forms with fields and submit actions, publish/unpublish them, or delete them.
     * Existing nested IDs are updated; omitted nested items remain unchanged. Use deleteFieldIds or
     * deleteActionIds with confirm=true for explicit nested deletion.
     */
    #[McpTool(name: 'mautic_manage_forms', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    #[Schema(definition: self::INPUT_SCHEMA)]
    public function __invoke(
        string $action,
        ?int $id = null,
        array $data = [],
        bool $confirm = false,
        bool $dryRun = false,
        ?string $idempotencyKey = null,
        ?string $expectedDateModified = null,
    ): array {
        $this->bootstrapExecution();
        $payload = compact('action', 'id', 'data', 'confirm', 'expectedDateModified');
        if ($dryRun) {
            return $this->mutations->dryRun('form', $action, $payload);
        }

        return $this->mutations->execute(
            'forms',
            $idempotencyKey,
            $payload,
            fn (): array => $this->service->write($action, $id, $data, $confirm, $expectedDateModified),
        );
    }
}
