<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Management;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Membership\MembershipManager;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\ListModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class MauticManagementService
{
    public function __construct(
        private LeadModel $leadModel,
        private ListModel $listModel,
        private CampaignModel $campaignModel,
        private EmailModel $emailModel,
        private MembershipManager $membershipManager,
        private CorePermissions $permissions,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function contacts(string $action, ?int $id, array $data, string $query, int $limit, int $page, bool $confirm, ?string $expectedDateModified = null): array
    {
        return match ($action) {
            'list'   => $this->listContacts($query, $limit, $page),
            'get'    => $this->contactResult($this->contact($id), 'fetched'),
            'create' => $this->saveContact($this->leadModel->getEntity(), $data, true),
            'update' => $this->saveContact($this->contactForWrite($id, $expectedDateModified), $data, false),
            'delete' => $this->deleteContact($this->contactForWrite($id, $expectedDateModified), $confirm),
            default  => throw new BadRequestHttpException('Unsupported contact action. Use list, get, create, update, or delete.'),
        };
    }

    public function segments(string $action, ?int $id, array $data, array $contactIds, string $query, int $limit, int $page, bool $confirm, ?string $expectedDateModified = null): array
    {
        return match ($action) {
            'list'            => $this->listSegments($query, $limit, $page),
            'get'             => $this->segmentResult($this->segment($id), 'fetched'),
            'create'          => $this->saveSegment($this->listModel->getEntity(), $data, true),
            'update'          => $this->saveSegment($this->segmentForWrite($id, $expectedDateModified), $data, false),
            'delete'          => $this->deleteSegment($this->segmentForWrite($id, $expectedDateModified), $confirm),
            'add_contacts'    => $this->changeSegmentContacts($this->segmentForWrite($id, $expectedDateModified), $contactIds, true),
            'remove_contacts' => $this->changeSegmentContacts($this->segmentForWrite($id, $expectedDateModified), $contactIds, false),
            default           => throw new BadRequestHttpException('Unsupported segment action.'),
        };
    }

    public function campaigns(string $action, ?int $id, array $data, array $contactIds, array $segmentIds, string $query, int $limit, int $page, bool $confirm, ?string $expectedDateModified = null): array
    {
        return match ($action) {
            'list'            => $this->listCampaigns($query, $limit, $page),
            'get'             => $this->campaignResult($this->campaign($id), 'fetched'),
            'create'          => $this->saveCampaign($this->campaignModel->getEntity(), $data, $segmentIds, true),
            'update'          => $this->saveCampaign($this->campaignForWrite($id, $expectedDateModified), $data, $segmentIds, false),
            'delete'          => $this->deleteCampaign($this->campaignForWrite($id, $expectedDateModified), $confirm),
            'publish'         => $this->publishCampaign($this->campaignForWrite($id, $expectedDateModified), true),
            'unpublish'       => $this->publishCampaign($this->campaignForWrite($id, $expectedDateModified), false),
            'add_contacts'    => $this->changeCampaignContacts($this->campaignForWrite($id, $expectedDateModified), $contactIds, true),
            'remove_contacts' => $this->changeCampaignContacts($this->campaignForWrite($id, $expectedDateModified), $contactIds, false),
            'set_segments'    => $this->setCampaignSegments($this->campaignForWrite($id, $expectedDateModified), $segmentIds),
            default           => throw new BadRequestHttpException('Unsupported campaign action.'),
        };
    }

    public function emails(string $action, ?int $id, array $data, array $contactIds, array $segmentIds, string $query, int $limit, int $page, bool $confirm, ?string $expectedDateModified = null): array
    {
        return match ($action) {
            'list'             => $this->listEmails($query, $limit, $page),
            'get'              => $this->emailResult($this->email($id), 'fetched'),
            'create'           => $this->saveEmail($this->emailModel->getEntity(), $data, $segmentIds, true),
            'update'           => $this->saveEmail($this->emailForWrite($id, $expectedDateModified), $data, $segmentIds, false),
            'update_html'      => $this->updateEmailHtml($this->emailForWrite($id, $expectedDateModified), $data),
            'delete'           => $this->deleteEmail($this->emailForWrite($id, $expectedDateModified), $confirm),
            'publish'          => $this->publishEmail($this->emailForWrite($id, $expectedDateModified), true),
            'unpublish'        => $this->publishEmail($this->emailForWrite($id, $expectedDateModified), false),
            'enable_preview'   => $this->setEmailPublicPreview($this->emailForWrite($id, $expectedDateModified), true),
            'disable_preview'  => $this->setEmailPublicPreview($this->emailForWrite($id, $expectedDateModified), false),
            'lock'             => $this->lockEmail($this->emailForWrite($id, $expectedDateModified)),
            'unlock'           => $this->unlockEmail($this->emailForWrite($id, $expectedDateModified), $confirm),
            'set_segments'     => $this->setEmailSegments($this->emailForWrite($id, $expectedDateModified), $segmentIds),
            'clone'            => $this->cloneEmail($this->emailForWrite($id, $expectedDateModified), $data, $segmentIds),
            'create_ab_test'   => $this->createAbTest($this->emailForWrite($id, $expectedDateModified), $data),
            'send_test'        => $this->sendTestEmail($this->emailForWrite($id, $expectedDateModified), $data, $confirm),
            'send_to_contacts' => $this->sendEmailToContacts($this->emailForWrite($id, $expectedDateModified), $contactIds, $confirm),
            'send_to_segments' => $this->sendEmailToSegments($this->emailForWrite($id, $expectedDateModified), $segmentIds, $confirm),
            default            => throw new BadRequestHttpException('Unsupported email action.'),
        };
    }

    private function listContacts(string $query, int $limit, int $page): array
    {
        $this->assertGrantedAny(['lead:leads:viewown', 'lead:leads:viewother']);
        $items = $this->leadModel->getEntities($this->listArgs('l', $query, $limit, $page));

        return $this->page($items, fn (Lead $lead): array => $this->contactData($lead), $page, $limit);
    }

    private function listSegments(string $query, int $limit, int $page): array
    {
        $this->assertGrantedAny(['lead:lists:viewown', 'lead:lists:viewother']);
        $items = $this->listModel->getEntities($this->listArgs('l', $query, $limit, $page));

        return $this->page($items, fn (LeadList $segment): array => $this->segmentData($segment), $page, $limit);
    }

    private function listCampaigns(string $query, int $limit, int $page): array
    {
        $this->assertGrantedAny(['campaign:campaigns:viewown', 'campaign:campaigns:viewother']);
        $items = $this->campaignModel->getEntities($this->listArgs('c', $query, $limit, $page));

        return $this->page($items, fn (Campaign $campaign): array => $this->campaignData($campaign), $page, $limit);
    }

    private function listEmails(string $query, int $limit, int $page): array
    {
        $this->assertGrantedAny(['email:emails:viewown', 'email:emails:viewother']);
        $items = $this->emailModel->getEntities($this->listArgs('e', $query, $limit, $page));

        return $this->page($items, fn (Email $email): array => $this->emailData($email), $page, $limit);
    }

    private function saveContact(?Lead $contact, array $data, bool $new): array
    {
        if (!$contact instanceof Lead) {
            throw new \RuntimeException('Unable to initialize contact.');
        }
        $this->assertEntityPermission('lead:leads', $new ? 'create' : 'edit', $contact->getPermissionUser());
        if ([] === $data) {
            throw new BadRequestHttpException('Contact data cannot be empty.');
        }
        $this->leadModel->setFieldValues($contact, $data, true, false);
        if (array_key_exists('points', $data)) {
            $contact->setPoints((int) $data['points']);
        }
        $this->leadModel->saveEntity($contact);

        return $this->contactResult($contact, $new ? 'created' : 'updated');
    }

    private function saveSegment(?LeadList $segment, array $data, bool $new): array
    {
        if (!$segment instanceof LeadList) {
            throw new \RuntimeException('Unable to initialize segment.');
        }
        $this->assertEntityPermission('lead:lists', $new ? 'create' : 'edit', $segment->getCreatedBy());
        if (isset($data['name'])) {
            $segment->setName((string) $data['name']);
        }
        if (isset($data['publicName']) || isset($data['name'])) {
            $segment->setPublicName((string) ($data['publicName'] ?? $data['name']));
        }
        if (isset($data['description'])) {
            $segment->setDescription((string) $data['description']);
        }
        if (isset($data['alias'])) {
            $segment->setAlias((string) $data['alias']);
        } elseif ($new && isset($data['name'])) {
            $segment->setAlias($this->alias((string) $data['name']));
        }
        if (isset($data['filters']) && is_array($data['filters'])) {
            $segment->setFilters($data['filters']);
        }
        if (array_key_exists('isGlobal', $data)) {
            $segment->setIsGlobal((bool) $data['isGlobal']);
        }
        if (array_key_exists('isPreferenceCenter', $data)) {
            $segment->setIsPreferenceCenter((bool) $data['isPreferenceCenter']);
        }
        if ('' === trim((string) $segment->getName())) {
            throw new BadRequestHttpException('Segment name is required.');
        }
        $this->listModel->saveEntity($segment);

        return $this->segmentResult($segment, $new ? 'created' : 'updated');
    }

    private function saveCampaign(?Campaign $campaign, array $data, array $segmentIds, bool $new): array
    {
        if (!$campaign instanceof Campaign) {
            throw new \RuntimeException('Unable to initialize campaign.');
        }
        $this->assertEntityPermission('campaign:campaigns', $new ? 'create' : 'edit', $campaign->getCreatedBy());
        if (isset($data['name'])) {
            $campaign->setName((string) $data['name']);
        }
        if (isset($data['description'])) {
            $campaign->setDescription((string) $data['description']);
        }
        if (array_key_exists('isPublished', $data)) {
            $campaign->setIsPublished((bool) $data['isPublished']);
        }
        if (array_key_exists('allowRestart', $data)) {
            $campaign->setAllowRestart((bool) $data['allowRestart']);
        }
        if (isset($data['publishUp'])) {
            $campaign->setPublishUp($this->date($data['publishUp']));
        }
        if (isset($data['publishDown'])) {
            $campaign->setPublishDown($this->date($data['publishDown']));
        }
        if ('' === trim((string) $campaign->getName())) {
            throw new BadRequestHttpException('Campaign name is required.');
        }
        if ([] !== $segmentIds) {
            $this->replaceCampaignSegments($campaign, $segmentIds);
        }
        $this->campaignModel->saveEntity($campaign);

        return $this->campaignResult($campaign, $new ? 'created' : 'updated');
    }

    private function saveEmail(?Email $email, array $data, array $segmentIds, bool $new): array
    {
        if (!$email instanceof Email) {
            throw new \RuntimeException('Unable to initialize email.');
        }
        $this->assertEntityPermission('email:emails', $new ? 'create' : 'edit', $email->getCreatedBy());
        $map = [
            'name' => 'setName', 'description' => 'setDescription', 'subject' => 'setSubject',
            'customHtml' => 'setCustomHtml', 'plainText' => 'setPlainText', 'fromName' => 'setFromName',
            'fromAddress' => 'setFromAddress', 'replyToAddress' => 'setReplyToAddress',
            'preheaderText' => 'setPreheaderText', 'template' => 'setTemplate', 'emailType' => 'setEmailType',
        ];
        foreach ($map as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $email->{$setter}($data[$field]);
            }
        }
        if ($new && !isset($data['emailType'])) {
            $email->setEmailType([] === $segmentIds ? 'template' : 'list');
        }
        if (array_key_exists('isPublished', $data)) {
            $email->setIsPublished((bool) $data['isPublished']);
        }
        if ('' === trim((string) $email->getName()) || '' === trim((string) $email->getSubject())) {
            throw new BadRequestHttpException('Email name and subject are required.');
        }
        if ([] !== $segmentIds) {
            $email->setLists($this->segmentsByIds($segmentIds));
            $email->setEmailType('list');
        }
        $this->emailModel->saveEntity($email);

        return $this->emailResult($email, $new ? 'created' : 'updated');
    }

    private function changeSegmentContacts(LeadList $segment, array $ids, bool $add): array
    {
        $this->assertEntityPermission('lead:lists', 'edit', $segment->getCreatedBy());
        $contacts = $this->contactsByIds($ids, true);
        foreach ($contacts as $contact) {
            $add ? $this->leadModel->addToLists($contact, $segment) : $this->leadModel->removeFromLists($contact, $segment);
        }

        return ['status' => $add ? 'contacts_added' : 'contacts_removed', 'segmentId' => $segment->getId(), 'contactIds' => array_keys($contacts)];
    }

    private function changeCampaignContacts(Campaign $campaign, array $ids, bool $add): array
    {
        $this->assertEntityPermission('campaign:campaigns', 'edit', $campaign->getCreatedBy());
        $contacts = $this->contactsByIds($ids, true);
        foreach ($contacts as $contact) {
            $add ? $this->membershipManager->addContact($contact, $campaign) : $this->membershipManager->removeContact($contact, $campaign);
        }

        return ['status' => $add ? 'contacts_added' : 'contacts_removed', 'campaignId' => $campaign->getId(), 'contactIds' => array_keys($contacts)];
    }

    private function setCampaignSegments(Campaign $campaign, array $ids): array
    {
        $this->assertEntityPermission('campaign:campaigns', 'edit', $campaign->getCreatedBy());
        $this->replaceCampaignSegments($campaign, $ids);
        $this->campaignModel->saveEntity($campaign);

        return $this->campaignResult($campaign, 'segments_updated');
    }

    private function replaceCampaignSegments(Campaign $campaign, array $ids): void
    {
        foreach ($campaign->getLists()->toArray() as $segment) {
            $campaign->removeList($segment);
        }
        foreach ($this->segmentsByIds($ids) as $segment) {
            $campaign->addList($segment);
        }
    }

    private function setEmailSegments(Email $email, array $ids): array
    {
        $this->assertEntityPermission('email:emails', 'edit', $email->getCreatedBy());
        $email->setLists($this->segmentsByIds($ids));
        $email->setEmailType('list');
        $this->emailModel->saveEntity($email);

        return $this->emailResult($email, 'segments_updated');
    }

    private function cloneEmail(Email $source, array $data, array $segmentIds): array
    {
        $this->assertGrantedAny(['email:emails:create']);
        $clone = clone $source;
        $clone->setEmailType($source->getEmailType());
        $clone->setIsPublished((bool) ($data['isPublished'] ?? false));
        $data['name'] ??= trim((string) $source->getName().' - Copy');

        return $this->saveEmail($clone, $data, $segmentIds, true) + ['sourceEmailId' => $source->getId()];
    }

    private function createAbTest(Email $parent, array $data): array
    {
        $this->assertEntityPermission('email:emails', 'edit', $parent->getCreatedBy());
        $this->assertGrantedAny(['email:emails:create']);
        $variants = $data['variants'] ?? null;
        if (!is_array($variants) || [] === $variants || count($variants) > 10) {
            throw new BadRequestHttpException('data.variants must contain between 1 and 10 variant definitions.');
        }
        $winnerCriteria = (string) ($data['winnerCriteria'] ?? 'email.openrate');
        $sendWinnerDelay = max(1, min(24, (int) ($data['sendWinnerDelay'] ?? 24)));
        $totalWeight = max(1, min(50, (int) ($data['totalWeight'] ?? 10)));
        $parent->setVariantSettings([
            'enableAbTest' => true,
            'winnerCriteria' => $winnerCriteria,
            'sendWinnerDelay' => $sendWinnerDelay,
            'totalWeight' => $totalWeight,
        ]);
        $this->emailModel->saveEntity($parent);

        $created = [];
        foreach ($variants as $index => $definition) {
            if (!is_array($definition)) {
                throw new BadRequestHttpException(sprintf('Variant at index %d must be an object.', $index));
            }
            $variant = clone $parent;
            $variant->setEmailType($parent->getEmailType());
            $variant->setVariantParent($parent);
            $variant->setVariantSettings(['weight' => max(1, min(100, (int) ($definition['weight'] ?? floor(100 / (count($variants) + 1))))), 'winnerCriteria' => $winnerCriteria]);
            $variant->setIsPublished((bool) ($definition['isPublished'] ?? false));
            $definition['name'] ??= sprintf('%s - Variant %s', $parent->getName(), chr(66 + $index));
            $result = $this->saveEmail($variant, $definition, [], true);
            $created[] = $result['email'];
        }

        return [
            'status' => 'ab_test_created',
            'parentEmailId' => $parent->getId(),
            'variantIds' => array_column($created, 'id'),
            'variantCount' => count($created),
            'winnerCriteria' => $winnerCriteria,
            'sendWinnerDelay' => $sendWinnerDelay,
            'totalWeight' => $totalWeight,
            'variants' => $created,
        ];
    }

    private function sendTestEmail(Email $email, array $data, bool $confirm): array
    {
        $this->requireConfirmation($confirm, 'Sending a test email requires confirm=true.');
        $this->assertEntityPermission('email:emails', 'view', $email->getCreatedBy());
        $recipients = array_values(array_unique(array_filter(array_map('strval', (array) ($data['recipients'] ?? [])), static fn (string $address): bool => false !== filter_var($address, FILTER_VALIDATE_EMAIL))));
        if ([] === $recipients || count($recipients) > 20) {
            throw new BadRequestHttpException('data.recipients must contain between 1 and 20 valid email addresses.');
        }
        $users = array_map(static fn (string $address): array => ['id' => '', 'firstname' => '', 'lastname' => '', 'email' => $address], $recipients);
        $leadFields = is_array($data['leadFields'] ?? null) ? $data['leadFields'] : [];
        $leadFields['email'] ??= $recipients[0];
        $errors = $this->emailModel->sendSampleEmailToUser($email, $users, $leadFields, [], [], false);
        $errors = is_array($errors) ? $errors : ['Unable to prepare the test email.'];

        return [
            'status' => [] === $errors ? 'test_sent' : 'test_failed',
            'emailId' => $email->getId(),
            'sentCount' => [] === $errors ? count($recipients) : 0,
            'failedCount' => [] === $errors ? 0 : count($recipients),
            'recipients' => $recipients,
            'errors' => $errors,
        ];
    }

    private function publishCampaign(Campaign $campaign, bool $published): array
    {
        $this->assertEntityPermission('campaign:campaigns', 'publish', $campaign->getCreatedBy());
        $campaign->setIsPublished($published);
        $this->campaignModel->saveEntity($campaign);

        return $this->campaignResult($campaign, $published ? 'published' : 'unpublished');
    }

    private function publishEmail(Email $email, bool $published): array
    {
        $this->assertEntityPermission('email:emails', 'publish', $email->getCreatedBy());
        $email->setIsPublished($published);
        $this->emailModel->saveEntity($email);

        return $this->emailResult($email, $published ? 'published' : 'unpublished');
    }

    private function updateEmailHtml(Email $email, array $data): array
    {
        if (!array_key_exists('customHtml', $data) || '' === trim((string) $data['customHtml'])) {
            throw new BadRequestHttpException('data.customHtml is required and cannot be empty.');
        }

        return $this->saveEmail($email, array_intersect_key($data, array_flip(['customHtml', 'plainText', 'preheaderText', 'template'])), [], false);
    }

    private function setEmailPublicPreview(Email $email, bool $enabled): array
    {
        $this->assertEntityPermission('email:emails', 'edit', $email->getCreatedBy());
        $email->setPublicPreview($enabled);
        $this->emailModel->saveEntity($email);

        return $this->emailResult($email, $enabled ? 'preview_enabled' : 'preview_disabled');
    }

    private function lockEmail(Email $email): array
    {
        $this->assertEntityPermission('email:emails', 'edit', $email->getCreatedBy());
        if ($this->emailModel->isLocked($email)) {
            throw new ConflictHttpException('Email is locked by another user.');
        }
        $this->emailModel->lockEntity($email);

        return $this->emailResult($email, 'locked');
    }

    private function unlockEmail(Email $email, bool $confirm): array
    {
        $this->requireConfirmation($confirm, 'Unlocking an email requires confirm=true.');
        $this->assertEntityPermission('email:emails', 'edit', $email->getCreatedBy());
        $this->emailModel->unlockEntity($email);

        return $this->emailResult($email, 'unlocked');
    }

    private function sendEmailToContacts(Email $email, array $ids, bool $confirm): array
    {
        $this->requireConfirmation($confirm, 'Sending email requires confirm=true.');
        $this->assertEntityPermission('email:emails', 'view', $email->getCreatedBy());
        $results = [];
        foreach ($this->contactsByIds($ids, false) as $contact) {
            $leadFields = array_merge(['id' => $contact->getId()], $contact->getProfileFields());
            if ($contact->getOwner()) {
                $leadFields['owner_id'] = $contact->getOwner()->getId();
            }
            $result = $this->emailModel->sendEmail($email, $leadFields, [
                'source' => ['mcp', 0], 'return_errors' => true, 'ignoreDNC' => $email->getSendToDnc(),
            ]);
            $results[$contact->getId()] = true === $result ? ['success' => true] : ['success' => false, 'error' => $result];
        }

        $successIds = array_map('intval', array_keys(array_filter($results, static fn (array $result): bool => true === $result['success'])));
        $failureIds = array_map('intval', array_keys(array_filter($results, static fn (array $result): bool => false === $result['success'])));

        return [
            'status'             => 'send_completed',
            'emailId'            => $email->getId(),
            'sentCount'          => count($successIds),
            'failedCount'        => count($failureIds),
            'successIds'         => $successIds,
            'failureIds'         => $failureIds,
            'rejectedRecipients' => array_intersect_key($results, array_flip($failureIds)),
            'results'            => $results,
        ];
    }

    private function sendEmailToSegments(Email $email, array $ids, bool $confirm): array
    {
        $this->requireConfirmation($confirm, 'Bulk sending requires confirm=true.');
        $this->assertEntityPermission('email:emails', 'view', $email->getCreatedBy());
        if (!$email->isPublished()) {
            throw new BadRequestHttpException('Email must be published before bulk sending.');
        }
        $segments = $this->segmentsByIds($ids);
        [$sent, $failed, $failedRecipients] = $this->emailModel->sendEmailToLists($email, $segments);

        return [
            'status'             => 'send_completed',
            'emailId'            => $email->getId(),
            'sentCount'          => (int) $sent,
            'failedCount'        => (int) $failed,
            'successIds'         => [],
            'failureIds'         => array_map('intval', array_keys($failedRecipients)),
            'rejectedRecipients' => $failedRecipients,
        ];
    }

    private function deleteContact(Lead $contact, bool $confirm): array
    {
        $this->requireConfirmation($confirm, 'Deleting a contact requires confirm=true.');
        $this->assertEntityPermission('lead:leads', 'delete', $contact->getPermissionUser());
        $id = $contact->getId();
        $this->leadModel->deleteEntity($contact);

        return ['status' => 'deleted', 'contactId' => $id];
    }

    private function deleteSegment(LeadList $segment, bool $confirm): array
    {
        $this->requireConfirmation($confirm, 'Deleting a segment requires confirm=true.');
        $this->assertEntityPermission('lead:lists', 'delete', $segment->getCreatedBy());
        $id = $segment->getId();
        $this->listModel->deleteEntity($segment);

        return ['status' => 'deleted', 'segmentId' => $id];
    }

    private function deleteCampaign(Campaign $campaign, bool $confirm): array
    {
        $this->requireConfirmation($confirm, 'Deleting a campaign requires confirm=true.');
        $this->assertEntityPermission('campaign:campaigns', 'delete', $campaign->getCreatedBy());
        $id = $campaign->getId();
        $this->campaignModel->deleteEntity($campaign);

        return ['status' => 'deleted', 'campaignId' => $id];
    }

    private function deleteEmail(Email $email, bool $confirm): array
    {
        $this->requireConfirmation($confirm, 'Deleting an email requires confirm=true.');
        $this->assertEntityPermission('email:emails', 'delete', $email->getCreatedBy());
        $id = $email->getId();
        $this->emailModel->deleteEntity($email);

        return ['status' => 'deleted', 'emailId' => $id];
    }

    private function contact(?int $id): Lead
    {
        $entity = null === $id ? null : $this->leadModel->getEntity($id);
        if (!$entity instanceof Lead) {
            throw new NotFoundHttpException(sprintf('Contact %d was not found.', $id ?? 0));
        }
        $this->assertEntityPermission('lead:leads', 'view', $entity->getPermissionUser());

        return $entity;
    }

    private function segment(?int $id): LeadList
    {
        $entity = null === $id ? null : $this->listModel->getEntity($id);
        if (!$entity instanceof LeadList) {
            throw new NotFoundHttpException(sprintf('Segment %d was not found.', $id ?? 0));
        }
        $this->assertEntityPermission('lead:lists', 'view', $entity->getCreatedBy());

        return $entity;
    }

    private function campaign(?int $id): Campaign
    {
        $entity = null === $id ? null : $this->campaignModel->getEntity($id);
        if (!$entity instanceof Campaign) {
            throw new NotFoundHttpException(sprintf('Campaign %d was not found.', $id ?? 0));
        }
        $this->assertEntityPermission('campaign:campaigns', 'view', $entity->getCreatedBy());

        return $entity;
    }

    private function email(?int $id): Email
    {
        $entity = null === $id ? null : $this->emailModel->getEntity($id);
        if (!$entity instanceof Email) {
            throw new NotFoundHttpException(sprintf('Email %d was not found.', $id ?? 0));
        }
        $this->assertEntityPermission('email:emails', 'view', $entity->getCreatedBy());

        return $entity;
    }

    private function contactForWrite(?int $id, ?string $expected): Lead
    {
        $entity = $this->contact($id);
        $this->assertExpectedDateModified($entity->getDateModified(), $expected);

        return $entity;
    }

    private function segmentForWrite(?int $id, ?string $expected): LeadList
    {
        $entity = $this->segment($id);
        $this->assertExpectedDateModified($entity->getDateModified(), $expected);

        return $entity;
    }

    private function campaignForWrite(?int $id, ?string $expected): Campaign
    {
        $entity = $this->campaign($id);
        $this->assertExpectedDateModified($entity->getDateModified(), $expected);

        return $entity;
    }

    private function emailForWrite(?int $id, ?string $expected): Email
    {
        $entity = $this->email($id);
        $this->assertExpectedDateModified($entity->getDateModified(), $expected);

        return $entity;
    }

    private function assertExpectedDateModified(?\DateTimeInterface $actual, ?string $expected): void
    {
        if (null === $expected || '' === trim($expected)) {
            return;
        }

        $expectedDate = new \DateTimeImmutable($expected);
        if (null === $actual || $actual->getTimestamp() !== $expectedDate->getTimestamp()) {
            throw new ConflictHttpException('The entity changed after it was read. Fetch it again and retry with the current dateModified.');
        }
    }

    private function contactsByIds(array $ids, bool $requireEdit): array
    {
        $items = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $contact = $this->contact($id);
            if ($requireEdit) {
                $this->assertEntityPermission('lead:leads', 'edit', $contact->getPermissionUser());
            }
            $items[$id] = $contact;
        }
        if ([] === $items) {
            throw new BadRequestHttpException('At least one contact ID is required.');
        }

        return $items;
    }

    private function segmentsByIds(array $ids): array
    {
        $items = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $items[$id] = $this->segment($id);
        }

        return $items;
    }

    private function assertEntityPermission(string $base, string $action, mixed $owner): void
    {
        if ('create' === $action) {
            if (!$this->permissions->isGranted($base.':create')) {
                throw new AccessDeniedException('Permission denied for '.$base.':create.');
            }

            return;
        }
        if (!$this->permissions->hasEntityAccess($base.':'.$action.'own', $base.':'.$action.'other', $owner)) {
            throw new AccessDeniedException('Permission denied for '.$base.':'.$action.'.');
        }
    }

    private function assertGrantedAny(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->permissions->checkPermissionExists($permission) && $this->permissions->isGranted($permission)) {
                return;
            }
        }
        throw new AccessDeniedException('Permission denied.');
    }

    private function listArgs(string $alias, string $query, int $limit, int $page): array
    {
        $limit = max(1, min(100, $limit));

        return ['start' => (max(1, $page) - 1) * $limit, 'limit' => $limit, 'filter' => trim($query), 'orderBy' => $alias.'.id', 'orderByDir' => 'DESC'];
    }

    private function page(iterable $entities, callable $normalizer, int $page, int $limit): array
    {
        $page  = max(1, $page);
        $limit = max(1, min(100, $limit));
        $total = is_countable($entities) ? count($entities) : null;
        $items = [];
        foreach ($entities as $entity) {
            $items[] = $normalizer($entity);
        }

        $total ??= count($items);

        return [
            'page'     => $page,
            'limit'    => $limit,
            'count'    => count($items),
            'total'    => $total,
            'hasMore'  => $page * $limit < $total,
            'nextPage' => $page * $limit < $total ? $page + 1 : null,
            'items'    => $items,
        ];
    }

    private function contactData(Lead $contact): array
    {
        return ['id' => $contact->getId(), 'name' => $contact->getName(), 'email' => $contact->getEmail(), 'points' => $contact->getPoints(), 'dateModified' => $contact->getDateModified()?->format(DATE_ATOM), 'fields' => $contact->getProfileFields()];
    }

    private function segmentData(LeadList $segment): array
    {
        return ['id' => $segment->getId(), 'name' => $segment->getName(), 'publicName' => $segment->getPublicName(), 'alias' => $segment->getAlias(), 'description' => $segment->getDescription(), 'filters' => $segment->getFilters(), 'isGlobal' => (bool) $segment->getIsGlobal(), 'dateModified' => $segment->getDateModified()?->format(DATE_ATOM)];
    }

    private function campaignData(Campaign $campaign): array
    {
        return ['id' => $campaign->getId(), 'name' => $campaign->getName(), 'description' => $campaign->getDescription(), 'isPublished' => $campaign->isPublished(), 'allowRestart' => $campaign->getAllowRestart(), 'segmentIds' => array_values(array_map(fn (LeadList $s): int => (int) $s->getId(), $campaign->getLists()->toArray())), 'eventCount' => $campaign->getEvents()->count(), 'dateModified' => $campaign->getDateModified()?->format(DATE_ATOM)];
    }

    private function emailData(Email $email): array
    {
        return ['id' => $email->getId(), 'name' => $email->getName(), 'subject' => $email->getSubject(), 'description' => $email->getDescription(), 'emailType' => $email->getEmailType(), 'isPublished' => $email->isPublished(), 'publicPreview' => (bool) $email->getPublicPreview(), 'previewUrl' => $this->urlGenerator->generate('mautic_email_preview', ['objectId' => $email->getId(), 'objectType' => 'email'], UrlGeneratorInterface::ABSOLUTE_URL), 'isLockedForCurrentUser' => $this->emailModel->isLocked($email), 'checkedOutAt' => $email->getCheckedOut()?->format(DATE_ATOM), 'checkedOutById' => $email->getCheckedOutBy(), 'template' => $email->getTemplate(), 'customHtml' => $email->getCustomHtml(), 'plainText' => $email->getPlainText(), 'preheaderText' => $email->getPreheaderText(), 'fromName' => $email->getFromName(), 'fromAddress' => $email->getFromAddress(), 'segmentIds' => array_values(array_map(fn (LeadList $s): int => (int) $s->getId(), $email->getLists()->toArray())), 'sentCount' => $email->getSentCount(), 'isVariant' => $email->isVariant(true), 'variantParentId' => $email->getVariantParent()?->getId(), 'variantIds' => array_values(array_map(static fn (Email $variant): int => (int) $variant->getId(), $email->getVariantChildren()->toArray())), 'variantSettings' => $email->getVariantSettings(), 'dateModified' => $email->getDateModified()?->format(DATE_ATOM)];
    }

    private function contactResult(Lead $contact, string $status): array { return ['status' => $status, 'contact' => $this->contactData($contact)]; }
    private function segmentResult(LeadList $segment, string $status): array { return ['status' => $status, 'segment' => $this->segmentData($segment)]; }
    private function campaignResult(Campaign $campaign, string $status): array { return ['status' => $status, 'campaign' => $this->campaignData($campaign)]; }
    private function emailResult(Email $email, string $status): array { return ['status' => $status, 'email' => $this->emailData($email)]; }

    private function requireConfirmation(bool $confirm, string $message): void
    {
        if (!$confirm) {
            throw new BadRequestHttpException($message);
        }
    }

    private function alias(string $name): string
    {
        $alias = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

        return '' === $alias ? 'segment-'.bin2hex(random_bytes(4)) : $alias;
    }

    private function date(mixed $value): ?\DateTime
    {
        return null === $value || '' === $value ? null : new \DateTime((string) $value);
    }
}
