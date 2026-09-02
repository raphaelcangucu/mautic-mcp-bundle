<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool\Meta;

use MauticPlugin\MauticMcpBundle\Application\Management\MutationExecutor;
use MauticPlugin\MauticMcpBundle\Mcp\Tool\AbstractMcpTool;
use MauticPlugin\MauticMetaBundle\Application\Consent\WhatsAppConsentRegistrationService;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

#[McpTool(
    name: 'register_landing_whatsapp_opt_in',
    annotations: new ToolAnnotations(
        readOnlyHint: false,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
    ),
    outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT,
)]
final class RegisterLandingWhatsAppOptInTool extends AbstractMcpTool
{
    public function __construct(
        private WhatsAppConsentRegistrationService $registration,
        private MutationExecutor $mutations,
    ) {
    }

    /**
     * Register one or more individually evidenced landing-page WhatsApp opt-ins.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'register_landing_whatsapp_opt_in',
        annotations: new ToolAnnotations(
            readOnlyHint: false,
            destructiveHint: false,
            idempotentHint: true,
            openWorldHint: false,
        ),
        outputSchema: \MauticPlugin\MauticMcpBundle\OutputSchemas::OBJECT,
    )]
    public function __invoke(
        ?int $contactId = null,
        ?int $assetId = null,
        ?string $email = null,
        ?string $phone = null,
        ?bool $consent = null,
        ?string $consentAt = null,
        ?string $business = null,
        ?string $locale = null,
        ?string $purpose = null,
        ?string $source = null,
        ?string $consentText = null,
        ?string $consentVersion = null,
        ?string $externalSubmissionId = null,
        ?string $pageUrl = null,
        #[Schema(type: 'array', items: ['type' => 'object', 'additionalProperties' => false, 'properties' => [
            'contactId' => ['type' => ['integer', 'null']],
            'assetId' => ['type' => 'integer'],
            'email' => ['type' => ['string', 'null']],
            'phone' => ['type' => 'string'],
            'consent' => ['type' => 'boolean'],
            'consentAt' => ['type' => 'string'],
            'business' => ['type' => 'string'],
            'locale' => ['type' => ['string', 'null']],
            'purpose' => ['type' => 'string'],
            'source' => ['type' => 'string'],
            'consentText' => ['type' => 'string'],
            'consentVersion' => ['type' => 'string'],
            'externalSubmissionId' => ['type' => 'string'],
            'pageUrl' => ['type' => ['string', 'null']],
        ]])]
        array $items = [],
        bool $dryRun = false,
        bool $confirm = false,
        ?string $idempotencyKey = null,
    ): array {
        $this->bootstrapExecution();

        if (!$dryRun && !$confirm) {
            return ['status' => 'rejected', 'errors' => [['field' => 'confirm', 'message' => 'confirm=true is required.']]];
        }
        if (!$dryRun && '' === trim((string) $idempotencyKey)) {
            return ['status' => 'rejected', 'errors' => [['field' => 'idempotencyKey', 'message' => 'idempotencyKey is required.']]];
        }

        if ([] === $items) {
            $items[] = compact(
                'contactId',
                'assetId',
                'email',
                'phone',
                'consent',
                'consentAt',
                'business',
                'locale',
                'purpose',
                'source',
                'consentText',
                'consentVersion',
                'externalSubmissionId',
                'pageUrl',
            );
        }

        $execute = function () use ($items, $dryRun): array {
            $results = [];
            foreach ($items as $index => $item) {
                $results[] = ['index' => $index] + $this->registration->register($item, $dryRun);
            }

            return [
                'status'  => 'processed',
                'dryRun'  => $dryRun,
                'count'   => count($results),
                'results' => $results,
            ];
        };

        return $dryRun
            ? $execute()
            : $this->mutations->execute(
                'landing_whatsapp_opt_in',
                $idempotencyKey,
                ['count' => count($items)],
                $execute,
            );
    }
}
