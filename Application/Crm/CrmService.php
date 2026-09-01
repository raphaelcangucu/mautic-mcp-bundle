<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Crm;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Deduplicate\ContactMerger;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\TagModel;
use Mautic\StageBundle\Entity\Stage;
use Mautic\StageBundle\Model\StageModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CrmService
{
    public function __construct(
        private CompanyModel $companyModel,
        private FieldModel $fieldModel,
        private TagModel $tagModel,
        private LeadModel $leadModel,
        private StageModel $stageModel,
        private ContactMerger $contactMerger,
        private CorePermissions $permissions,
    ) {}

    public function read(string $resource, ?int $id, string $query, int $limit, int $page): array
    {
        $this->assertRead($resource);
        $model = match ($resource) {
            'companies' => $this->companyModel,
            'fields'    => $this->fieldModel,
            'tags'      => $this->tagModel,
            'stages'    => $this->stageModel,
            default     => throw new BadRequestHttpException('Resource must be companies, fields, tags, or stages.'),
        };
        if (null !== $id) {
            $entity = $model->getEntity($id);
            if (null === $entity) {
                throw new NotFoundHttpException(sprintf('%s %d was not found.', $resource, $id));
            }

            return ['resource' => $resource, 'item' => $this->normalize($entity)];
        }

        $limit = max(1, min(100, $limit));
        $page  = max(1, $page);
        $entities = $model->getEntities(['start' => ($page - 1) * $limit, 'limit' => $limit, 'filter' => $query]);
        $total = is_countable($entities) ? count($entities) : null;
        $items = [];
        foreach ($entities as $entity) {
            $items[] = $this->normalize($entity);
        }
        $total ??= count($items);

        return ['resource' => $resource, 'page' => $page, 'limit' => $limit, 'count' => count($items), 'total' => $total, 'hasMore' => $page * $limit < $total, 'nextPage' => $page * $limit < $total ? $page + 1 : null, 'items' => $items];
    }

    public function write(string $action, ?int $id, array $data, array $contactIds, ?int $winnerContactId, ?int $loserContactId, bool $confirm, ?string $expectedDateModified): array
    {
        return match ($action) {
            'create_company'       => $this->saveCompany(null, $data),
            'update_company'       => $this->saveCompany($id, $data, $expectedDateModified),
            'add_company_contacts' => $this->companyContacts($id, $contactIds, true),
            'remove_company_contacts' => $this->companyContacts($id, $contactIds, false),
            'create_tag'           => $this->createTag($data),
            'add_tags'             => $this->changeTags($contactIds, $data, true),
            'remove_tags'          => $this->changeTags($contactIds, $data, false),
            'create_field'         => $this->saveField(null, $data),
            'update_field'         => $this->saveField($id, $data, $expectedDateModified),
            'set_points'           => $this->setPoints($contactIds, $data),
            'set_stage'            => $this->setStage($contactIds, $id),
            'merge_contacts'       => $this->mergeContacts($winnerContactId, $loserContactId, $confirm),
            default                => throw new BadRequestHttpException('Unsupported CRM write action.'),
        };
    }

    private function saveCompany(?int $id, array $data, ?string $expectedDateModified = null): array
    {
        $this->assertAny(['lead:leads:create', 'lead:leads:editown', 'lead:leads:editother']);
        $company = $this->companyModel->getEntity($id);
        if (!$company instanceof Company) {
            throw new NotFoundHttpException('Company was not found or could not be initialized.');
        }
        $this->assertExpectedDateModified($company, $expectedDateModified);
        $this->companyModel->setFieldValues($company, $data, true);
        $this->companyModel->saveEntity($company);

        return ['status' => null === $id ? 'created' : 'updated', 'company' => $this->normalize($company)];
    }

    private function companyContacts(?int $id, array $contactIds, bool $add): array
    {
        $company = $this->companyModel->getEntity($id);
        if (!$company instanceof Company) {
            throw new NotFoundHttpException('Company was not found.');
        }
        $success = [];
        $failed  = [];
        foreach (array_unique(array_map('intval', $contactIds)) as $contactId) {
            $contact = $this->leadModel->getEntity($contactId);
            if (!$contact instanceof Lead) {
                $failed[$contactId] = 'not_found';
                continue;
            }
            $add ? $this->companyModel->addLeadToCompany($company, $contact) : $this->companyModel->removeLeadFromCompany($company, $contact);
            $success[] = $contactId;
        }

        return $this->mutationResult($add ? 'contacts_added' : 'contacts_removed', $success, $failed);
    }

    private function createTag(array $data): array
    {
        $name = trim((string) ($data['tag'] ?? ''));
        if ('' === $name) {
            throw new BadRequestHttpException('data.tag is required.');
        }
        $tag = $this->tagModel->getRepository()->getTagByNameOrCreateNewOne($name);
        if (isset($data['description'])) {
            $tag->setDescription((string) $data['description']);
        }
        $this->tagModel->saveEntity($tag);

        return ['status' => 'created_or_existing', 'tag' => $this->normalize($tag)];
    }

    private function changeTags(array $contactIds, array $data, bool $add): array
    {
        $tagIds = array_values(array_unique(array_map('intval', $data['tagIds'] ?? [])));
        if ([] === $tagIds || [] === $contactIds) {
            throw new BadRequestHttpException('contactIds and data.tagIds are required.');
        }
        $result = $add ? $this->tagModel->getRepository()->addTagsToLeads($contactIds, $tagIds) : $this->tagModel->getRepository()->removeTagsFromLeads($contactIds, $tagIds);

        return ['status' => $add ? 'tags_added' : 'tags_removed', 'result' => $result];
    }

    private function saveField(?int $id, array $data, ?string $expectedDateModified = null): array
    {
        $field = $this->fieldModel->getEntity($id);
        if (!$field instanceof LeadField) {
            throw new NotFoundHttpException('Field was not found or could not be initialized.');
        }
        $this->assertExpectedDateModified($field, $expectedDateModified);
        foreach (['label' => 'setLabel', 'alias' => 'setAlias', 'type' => 'setType', 'object' => 'setObject', 'isRequired' => 'setIsRequired', 'isVisible' => 'setIsVisible', 'isListable' => 'setIsListable', 'properties' => 'setProperties'] as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $field->{$setter}($data[$key]);
            }
        }
        if (isset($data['group'])) {
            $field->setGroup((string) $data['group']);
        }
        $this->fieldModel->saveEntity($field);

        return ['status' => null === $id ? 'created' : 'updated', 'field' => $this->normalize($field)];
    }

    private function setPoints(array $contactIds, array $data): array
    {
        $points = (int) ($data['points'] ?? 0);
        $success = [];
        $failed = [];
        foreach ($contactIds as $contactId) {
            $contact = $this->leadModel->getEntity((int) $contactId);
            if (!$contact instanceof Lead) {
                $failed[(int) $contactId] = 'not_found';
                continue;
            }
            $contact->setPoints($points);
            $this->leadModel->saveEntity($contact);
            $success[] = (int) $contactId;
        }

        return $this->mutationResult('points_updated', $success, $failed);
    }

    private function setStage(array $contactIds, ?int $stageId): array
    {
        $stage = $this->stageModel->getEntity($stageId);
        if (!$stage instanceof Stage) {
            throw new NotFoundHttpException('Stage was not found.');
        }
        $success = [];
        $failed = [];
        foreach ($contactIds as $contactId) {
            $contact = $this->leadModel->getEntity((int) $contactId);
            if (!$contact instanceof Lead) {
                $failed[(int) $contactId] = 'not_found';
                continue;
            }
            $contact->setStage($stage);
            $this->leadModel->saveEntity($contact);
            $success[] = (int) $contactId;
        }

        return $this->mutationResult('stage_updated', $success, $failed);
    }

    private function mergeContacts(?int $winnerId, ?int $loserId, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Merging contacts requires confirm=true.');
        }
        $winner = $this->leadModel->getEntity($winnerId);
        $loser  = $this->leadModel->getEntity($loserId);
        if (!$winner instanceof Lead || !$loser instanceof Lead) {
            throw new NotFoundHttpException('Winner or loser contact was not found.');
        }
        $merged = $this->contactMerger->merge($winner, $loser);

        return ['status' => 'merged', 'winnerContactId' => $merged->getId(), 'deletedContactId' => $loserId];
    }

    private function normalize(object $entity): array
    {
        return match (true) {
            $entity instanceof Company   => ['id' => $entity->getId(), 'name' => $entity->getName(), 'email' => $entity->getEmail(), 'phone' => $entity->getPhone(), 'fields' => $entity->getProfileFields()],
            $entity instanceof LeadField => ['id' => $entity->getId(), 'label' => $entity->getLabel(), 'alias' => $entity->getAlias(), 'type' => $entity->getType(), 'object' => $entity->getObject(), 'group' => $entity->getGroup(), 'properties' => $entity->getProperties()],
            $entity instanceof Tag       => ['id' => $entity->getId(), 'tag' => $entity->getTag(), 'description' => $entity->getDescription()],
            $entity instanceof Stage     => ['id' => $entity->getId(), 'name' => $entity->getName(), 'description' => $entity->getDescription(), 'weight' => $entity->getWeight(), 'isPublished' => $entity->isPublished()],
            default                      => throw new \LogicException('Unsupported CRM entity.'),
        };
    }

    private function mutationResult(string $status, array $success, array $failed): array
    {
        return ['status' => $status, 'successIds' => $success, 'failureIds' => array_map('intval', array_keys($failed)), 'successCount' => count($success), 'failureCount' => count($failed), 'failures' => $failed];
    }

    private function assertExpectedDateModified(object $entity, ?string $expected): void
    {
        if (null === $expected || !method_exists($entity, 'getDateModified')) {
            return;
        }
        $dateModified = $entity->getDateModified();
        if (!$dateModified instanceof \DateTimeInterface || $dateModified->getTimestamp() !== (new \DateTimeImmutable($expected))->getTimestamp()) {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('CRM entity changed since expectedDateModified. Read it again before writing.');
        }
    }

    private function assertRead(string $resource): void
    {
        $this->assertAny('stages' === $resource ? ['stage:stages:viewown', 'stage:stages:viewother'] : ['lead:leads:viewown', 'lead:leads:viewother']);
    }

    private function assertAny(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->permissions->checkPermissionExists($permission) && $this->permissions->isGranted($permission)) {
                return;
            }
        }
        throw new AccessDeniedException('Permission denied.');
    }
}
