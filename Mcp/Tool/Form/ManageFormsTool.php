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
    public function __construct(
        private FormWriteService $service,
        private MutationExecutor $mutations,
    ) {}

    /**
     * Create/update forms with fields and submit actions, publish/unpublish them, or delete them.
     * Existing nested IDs are updated; omitted nested items remain unchanged. Use deleteFieldIds or
     * deleteActionIds with confirm=true for explicit nested deletion.
     */
    #[McpTool(name: 'mautic_manage_forms', annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false), outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT)]
    public function __invoke(
        #[Schema(enum: ['create', 'update', 'delete', 'publish', 'unpublish'])]
        string $action,
        ?int $id = null,
        #[Schema(
            type: 'object',
            properties: [
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
                'progressiveProfilingLimit' => ['type' => ['integer', 'null']],
                'submissionLimit' => ['type' => ['integer', 'null']],
                'submissionLimitMessage' => ['type' => ['string', 'null']],
                'categoryId' => ['type' => ['integer', 'null'], 'minimum' => 1],
                'fields' => [
                    'type' => 'array',
                    'maxItems' => 200,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'minimum' => 1],
                            'label' => ['type' => 'string'],
                            'showLabel' => ['type' => 'boolean'],
                            'alias' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'defaultValue' => ['type' => ['string', 'null']],
                            'isRequired' => ['type' => 'boolean'],
                            'validationMessage' => ['type' => ['string', 'null']],
                            'helpMessage' => ['type' => ['string', 'null']],
                            'order' => ['type' => 'integer', 'minimum' => 1],
                            'fieldOrder' => ['type' => 'integer', 'minimum' => 1],
                            'properties' => ['type' => 'object', 'additionalProperties' => true],
                            'validation' => ['type' => 'object', 'additionalProperties' => true],
                            'conditions' => ['type' => 'object', 'additionalProperties' => true],
                            'parent' => ['type' => ['string', 'integer', 'null']],
                            'labelAttributes' => ['type' => ['string', 'null']],
                            'inputAttributes' => ['type' => ['string', 'null']],
                            'containerAttributes' => ['type' => ['string', 'null']],
                            'leadField' => ['type' => ['string', 'null']],
                            'saveResult' => ['type' => 'boolean'],
                            'isAutoFill' => ['type' => 'boolean'],
                            'isReadOnly' => ['type' => 'boolean'],
                            'showWhenValueExists' => ['type' => 'boolean'],
                            'showAfterXSubmissions' => ['type' => ['integer', 'null']],
                            'alwaysDisplay' => ['type' => 'boolean'],
                            'mappedObject' => ['type' => ['string', 'null']],
                            'mappedField' => ['type' => ['string', 'null']],
                            'fieldWidth' => ['type' => 'string'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'actions' => [
                    'type' => 'array',
                    'maxItems' => 100,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'minimum' => 1],
                            'name' => ['type' => ['string', 'null']],
                            'description' => ['type' => ['string', 'null']],
                            'type' => ['type' => 'string'],
                            'order' => ['type' => 'integer', 'minimum' => 1],
                            'actionOrder' => ['type' => 'integer', 'minimum' => 1],
                            'properties' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'deleteFieldIds' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'uniqueItems' => true],
                'deleteActionIds' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'uniqueItems' => true],
            ],
            additionalProperties: false,
        )]
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
