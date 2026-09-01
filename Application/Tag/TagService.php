<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Tag;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Model\TagModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class TagService
{
    public function __construct(
        private TagModel $model,
        private Connection $connection,
        private CorePermissions $permissions
    ) {}

    public function read(string $action, ?int $id, string $query, int $page, int $limit): array
    {
        $this->assertPermission('view');
        if ('get' === $action) {
            return ['tag' => $this->normalize($this->tag($id))];
        }
        if ('list' !== $action) {
            throw new BadRequestHttpException('Action must be list or get.');
        }
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $where = '' === trim($query) ? '' : ' WHERE t.tag LIKE :query OR t.description LIKE :query';
        $params = '' === $where ? [] : ['query' => '%'.$query.'%'];
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.MAUTIC_TABLE_PREFIX.'lead_tags t'.$where, $params);
        $items = $this->connection->fetchAllAssociative('SELECT t.id,t.tag,t.description,COUNT(DISTINCT x.lead_id) contactCount FROM '.MAUTIC_TABLE_PREFIX.'lead_tags t LEFT JOIN '.MAUTIC_TABLE_PREFIX.'lead_tags_xref x ON x.tag_id=t.id'.$where.' GROUP BY t.id,t.tag,t.description ORDER BY t.tag LIMIT :limit OFFSET :offset', [...$params, 'limit' => $limit, 'offset' => ($page - 1) * $limit], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER]);
        $hasMore = $page * $limit < $total;

        return ['page' => $page, 'limit' => $limit, 'count' => count($items), 'total' => $total, 'hasMore' => $hasMore, 'nextPage' => $hasMore ? $page + 1 : null, 'items' => $items];
    }

    public function write(string $action, ?int $id, array $data, array $contactIds, bool $confirm): array
    {
        return match ($action) {
            'create'          => $this->save(null, $data),
            'update'          => $this->save($id, $data),
            'delete'          => $this->delete($id, $confirm),
            'add_contacts'    => $this->contacts($id, $contactIds, true),
            'remove_contacts' => $this->contacts($id, $contactIds, false),
            default           => throw new BadRequestHttpException('Action must be create, update, delete, add_contacts, or remove_contacts.'),
        };
    }

    private function save(?int $id, array $data): array
    {
        $this->assertPermission(null === $id ? 'create' : 'edit');
        $name = trim((string) ($data['tag'] ?? ''));
        if ('' === $name) {
            throw new BadRequestHttpException('data.tag is required.');
        }
        $tag = null === $id ? $this->model->getRepository()->getTagByNameOrCreateNewOne($name) : $this->tag($id);
        $tag->setTag($name);
        if (array_key_exists('description', $data)) {
            $tag->setDescription((string) $data['description']);
        }
        $isNew = null === $tag->getId();
        $this->model->saveEntity($tag);

        return ['status' => $isNew ? 'created' : 'updated', 'tag' => $this->normalize($tag)];
    }

    private function delete(?int $id, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Deleting a tag requires confirm=true.');
        }
        $this->assertPermission('delete');
        $tag = $this->tag($id);
        $deletedId = (int) $tag->getId();
        $this->model->deleteEntity($tag);

        return ['status' => 'deleted', 'successIds' => [$deletedId], 'failureIds' => []];
    }

    private function contacts(?int $id, array $contactIds, bool $add): array
    {
        $this->assertPermission('edit');
        $tag = $this->tag($id);
        $ids = array_values(array_unique(array_filter(array_map('intval', $contactIds), static fn (int $contactId): bool => $contactId > 0)));
        if ([] === $ids) {
            throw new BadRequestHttpException('contactIds must contain at least one contact ID.');
        }
        $result = $add ? $this->model->getRepository()->addTagsToLeads($ids, [(int) $tag->getId()]) : $this->model->getRepository()->removeTagsFromLeads($ids, [(int) $tag->getId()]);
        $successIds = array_map('intval', array_keys($result));
        $failureIds = array_values(array_diff($ids, $successIds));

        return ['status' => $add ? 'contacts_tagged' : 'contacts_untagged', 'tagId' => $tag->getId(), 'successIds' => $successIds, 'failureIds' => $failureIds, 'successCount' => count($successIds), 'failureCount' => count($failureIds)];
    }

    private function tag(?int $id): Tag
    {
        $tag = null === $id ? null : $this->model->getEntity($id);
        if (!$tag instanceof Tag) {
            throw new NotFoundHttpException(sprintf('Tag %d was not found.', $id ?? 0));
        }

        return $tag;
    }

    private function normalize(Tag $tag): array
    {
        return ['id' => $tag->getId(), 'tag' => $tag->getTag(), 'description' => $tag->getDescription()];
    }

    private function assertPermission(string $level): void
    {
        $permission = 'tagManager:tagManager:'.$level;
        if (!$this->permissions->checkPermissionExists($permission) || !$this->permissions->isGranted($permission)) {
            throw new AccessDeniedException('Permission denied for '.$permission.'.');
        }
    }
}
