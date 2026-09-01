<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Contact;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticMcpBundle\Presentation\Normalizer\ContactNormalizer;
use MauticPlugin\MauticMcpBundle\Security\PermissionChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactReadService
{
    public function __construct(
        private LeadModel $leadModel,
        private ContactNormalizer $contactNormalizer,
        private PermissionChecker $permissionChecker,
        private UserHelper $userHelper,
    ) {
    }

    public function search(ContactSearchQuery $query): array
    {
        $this->permissionChecker->assertCanSearchContacts();

        $results = $this->leadModel->getEntities(
            [
                'start'      => $query->getStart(),
                'limit'      => $query->getLimit(),
                'filter'     => $this->buildFilter($query),
                'orderBy'    => 'l.id',
                'orderByDir' => 'DESC',
            ]
        );

        return $this->normalizeSearchResults($results, $query);
    }

    public function fetch(int $contactId): array
    {
        $contact = $this->getViewableContactOrFail($contactId);

        return $this->contactNormalizer->normalizeDetails($contact);
    }

    public function getViewableContactOrFail(int $contactId): Lead
    {
        $contact = $this->getContactOrFail($contactId);
        $this->permissionChecker->assertCanViewContact($contact);

        return $contact;
    }

    public function getContactOrFail(int $contactId): Lead
    {
        $contact = $this->leadModel->getEntity($contactId);

        if (!$contact instanceof Lead) {
            throw new NotFoundHttpException(sprintf('Contact %d was not found.', $contactId));
        }

        return $contact;
    }

    private function buildFilter(ContactSearchQuery $query): array|string
    {
        $filter = $query->getSearch();

        if ($this->permissionChecker->canViewOtherContacts()) {
            return $filter;
        }

        $currentUser = $this->userHelper->getUser();
        if (null === $currentUser || null === $currentUser->getId()) {
            return [
                'string' => $filter,
                'force'  => [
                    [
                        'column' => 'l.id',
                        'expr'   => 'eq',
                        'value'  => 0,
                    ],
                ],
            ];
        }

        return [
            'string' => $filter,
            'force'  => [
                [
                    'column' => 'l.createdBy',
                    'expr'   => 'eq',
                    'value'  => $currentUser->getId(),
                ],
            ],
        ];
    }

    private function normalizeSearchResults(iterable $results, ContactSearchQuery $query): array
    {
        $total = is_countable($results) ? count($results) : null;
        $items = [];

        foreach ($results as $result) {
            if ($result instanceof Lead) {
                $items[] = $this->contactNormalizer->normalizeSummary($result);
            }
        }

        $total ??= count($items);

        return [
            'query' => $query->getSearch(),
            'page'  => $query->getPage(),
            'limit' => $query->getLimit(),
            'count' => count($items),
            'total' => $total,
            'hasMore' => $query->getPage() * $query->getLimit() < $total,
            'nextPage' => $query->getPage() * $query->getLimit() < $total ? $query->getPage() + 1 : null,
            'items' => $items,
        ];
    }
}
