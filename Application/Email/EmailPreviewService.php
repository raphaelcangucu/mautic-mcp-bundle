<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Email;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Helper\TokenHelper;
use Mautic\LeadBundle\Model\LeadModel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class EmailPreviewService
{
    public function __construct(
        private EmailModel $emailModel,
        private LeadModel $leadModel,
        private Connection $connection,
        private CorePermissions $permissions,
    ) {}

    public function preview(int $emailId, array $contactIds, array $segmentIds, int $sampleSize): array
    {
        $email = $this->emailModel->getEntity($emailId);
        if (!$email instanceof Email) {
            throw new NotFoundHttpException(sprintf('Email %d was not found.', $emailId));
        }
        if (!$this->permissions->hasEntityAccess('email:emails:viewown', 'email:emails:viewother', $email->getCreatedBy())) {
            throw new AccessDeniedException('Permission denied for email preview.');
        }

        if ([] === $contactIds && [] === $segmentIds) {
            $segmentIds = array_map(static fn ($segment): int => (int) $segment->getId(), $email->getLists()->toArray());
        }
        $recipientIds = $this->resolveRecipientIds($contactIds, $segmentIds);
        if ([] === $recipientIds) {
            return $this->emptyPreview($email, $segmentIds);
        }

        $dncRows = $this->connection->executeQuery(
            'SELECT lead_id, reason FROM '.MAUTIC_TABLE_PREFIX.'lead_donotcontact WHERE channel = :channel AND lead_id IN (:ids)',
            ['channel' => 'email', 'ids' => $recipientIds],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();
        $dnc = [];
        foreach ($dncRows as $row) {
            $dnc[(int) $row['lead_id']] = (int) $row['reason'];
        }

        $invalid = [];
        $rejected = [];
        $eligible = [];
        $samples = [];
        foreach ($recipientIds as $contactId) {
            $contact = $this->leadModel->getEntity($contactId);
            if (!$contact instanceof Lead) {
                $rejected[$contactId] = 'not_found';
                continue;
            }
            $address = trim((string) $contact->getEmail());
            if (false === filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $contactId;
                $rejected[$contactId] = 'invalid_email';
                continue;
            }
            if (isset($dnc[$contactId])) {
                $rejected[$contactId] = DoNotContact::BOUNCED === $dnc[$contactId] ? 'bounced' : 'unsubscribed_or_dnc';
                continue;
            }
            $eligible[] = $contactId;
            if (count($samples) < max(1, min(10, $sampleSize))) {
                $samples[] = $this->renderSample($email, $contact);
            }
        }

        return [
            'emailId'                    => $emailId,
            'emailName'                  => $email->getName(),
            'subject'                    => $email->getSubject(),
            'segmentIds'                 => array_values(array_unique(array_map('intval', $segmentIds))),
            'explicitContactIds'         => array_values(array_unique(array_map('intval', $contactIds))),
            'estimatedRecipients'        => count($recipientIds),
            'estimatedEligible'          => count($eligible),
            'invalidEmailCount'           => count($invalid),
            'unsubscribedOrBouncedCount' => count($dnc),
            'eligibleContactIds'          => $eligible,
            'rejectedRecipients'         => $rejected,
            'samples'                    => $samples,
            'safeToSend'                 => [] !== $eligible && $email->isPublished(),
            'warnings'                   => $email->isPublished() ? [] : ['Email is not published.'],
        ];
    }

    private function resolveRecipientIds(array $contactIds, array $segmentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $contactIds))));
        $segmentIds = array_values(array_unique(array_filter(array_map('intval', $segmentIds))));
        if ([] !== $segmentIds) {
            $rows = $this->connection->executeQuery(
                'SELECT DISTINCT lead_id FROM '.MAUTIC_TABLE_PREFIX.'lead_lists_leads WHERE leadlist_id IN (:ids) AND manually_removed = 0',
                ['ids' => $segmentIds],
                ['ids' => ArrayParameterType::INTEGER],
            )->fetchFirstColumn();
            $ids = array_merge($ids, array_map('intval', $rows));
        }

        return array_values(array_unique($ids));
    }

    private function renderSample(Email $email, Lead $contact): array
    {
        $fields  = $contact->getProfileFields();
        $subject = (string) TokenHelper::findLeadTokens((string) $email->getSubject(), $fields, true);
        $html    = (string) TokenHelper::findLeadTokens((string) $email->getCustomHtml(), $fields, true);
        preg_match_all(TokenHelper::REGEX, $subject."\n".$html, $matches);

        return [
            'contactId'    => $contact->getId(),
            'subject'      => $subject,
            'html'         => mb_substr($html, 0, 5000),
            'missingTokens' => array_values(array_unique($matches[0])),
        ];
    }

    private function emptyPreview(Email $email, array $segmentIds): array
    {
        return [
            'emailId' => $email->getId(), 'emailName' => $email->getName(), 'segmentIds' => $segmentIds,
            'estimatedRecipients' => 0, 'estimatedEligible' => 0, 'invalidEmailCount' => 0,
            'unsubscribedOrBouncedCount' => 0, 'eligibleContactIds' => [], 'rejectedRecipients' => [],
            'samples' => [], 'safeToSend' => false, 'warnings' => ['No recipients resolved.'],
        ];
    }
}
