<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Form;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Model\FormModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class FormReadService
{
    public function __construct(
        private FormModel $formModel,
        private Connection $connection,
        private CorePermissions $permissions,
        private FormNormalizer $normalizer,
    ) {}

    public function read(string $action, ?int $formId, ?int $contactId, int $page, int $limit): array
    {
        $this->assertRead();
        $page  = max(1, $page);
        $limit = max(1, min(100, $limit));

        return match ($action) {
            'list'                => $this->list($page, $limit),
            'get'                 => $this->get($formId),
            'submissions'         => $this->submissions($formId, null, $page, $limit),
            'contact_submissions' => $this->submissions($formId, $contactId, $page, $limit),
            default               => throw new BadRequestHttpException('Action must be list, get, submissions, or contact_submissions.'),
        };
    }

    private function list(int $page, int $limit): array
    {
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.MAUTIC_TABLE_PREFIX.'forms');
        $items = $this->connection->fetchAllAssociative(
            'SELECT id, name, alias, description, form_type AS formType, is_published AS isPublished, submission_count AS submissionCount, date_added AS dateAdded, date_modified AS dateModified FROM '.MAUTIC_TABLE_PREFIX.'forms ORDER BY id DESC LIMIT :limit OFFSET :offset',
            ['limit' => $limit, 'offset' => ($page - 1) * $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return $this->page($items, $total, $page, $limit);
    }

    private function get(?int $formId): array
    {
        if (null === $formId || $formId < 1) {
            throw new BadRequestHttpException('formId is required for action=get.');
        }
        $form = $this->formModel->getEntity($formId);
        if (!$form instanceof Form) {
            throw new NotFoundHttpException('Form was not found.');
        }
        if (!$this->permissions->hasEntityAccess('form:forms:viewown', 'form:forms:viewother', $form->getCreatedBy())) {
            throw new AccessDeniedException('Permission denied.');
        }

        return $this->normalizer->normalize($form);
    }

    private function submissions(?int $formId, ?int $contactId, int $page, int $limit): array
    {
        if (null === $formId && null === $contactId) {
            throw new BadRequestHttpException('formId or contactId is required.');
        }
        $where = [];
        $params = [];
        if (null !== $formId) {
            $where[] = 's.form_id = :formId';
            $params['formId'] = $formId;
        }
        if (null !== $contactId) {
            $where[] = 's.lead_id = :contactId';
            $params['contactId'] = $contactId;
        }
        $condition = implode(' AND ', $where);
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.MAUTIC_TABLE_PREFIX.'form_submissions s WHERE '.$condition, $params);
        $items = $this->connection->fetchAllAssociative(
            'SELECT s.id, s.form_id AS formId, f.name AS formName, s.lead_id AS contactId, s.tracking_id AS trackingId, s.date_submitted AS dateSubmitted, s.referer FROM '.MAUTIC_TABLE_PREFIX.'form_submissions s INNER JOIN '.MAUTIC_TABLE_PREFIX.'forms f ON f.id = s.form_id WHERE '.$condition.' ORDER BY s.id DESC LIMIT :limit OFFSET :offset',
            [...$params, 'limit' => $limit, 'offset' => ($page - 1) * $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return $this->page($items, $total, $page, $limit);
    }

    private function page(array $items, int $total, int $page, int $limit): array
    {
        $hasMore = $page * $limit < $total;

        return ['page' => $page, 'limit' => $limit, 'count' => count($items), 'total' => $total, 'hasMore' => $hasMore, 'nextPage' => $hasMore ? $page + 1 : null, 'items' => $items];
    }

    private function assertRead(): void
    {
        foreach (['form:forms:viewown', 'form:forms:viewother'] as $permission) {
            if ($this->permissions->isGranted($permission)) {
                return;
            }
        }
        throw new AccessDeniedException('Permission denied.');
    }
}
