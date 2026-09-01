<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Campaign;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CoreBundle\Helper\UserHelper;
use MauticPlugin\MauticMcpBundle\Presentation\Normalizer\CampaignNormalizer;
use MauticPlugin\MauticMcpBundle\Security\PermissionChecker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CampaignReadService
{
    public function __construct(
        private CampaignModel $campaignModel,
        private CampaignNormalizer $campaignNormalizer,
        private PermissionChecker $permissionChecker,
        private UserHelper $userHelper,
    ) {
    }

    public function search(CampaignSearchQuery $query): array
    {
        $this->permissionChecker->assertCanSearchCampaigns();

        $results = $this->campaignModel->getEntities(
            [
                'start'  => $query->getStart(),
                'limit'  => $query->getLimit(),
                'filter' => $this->buildFilter($query),
                'orderBy' => 'c.id',
                'orderByDir' => 'DESC',
            ]
        );

        $total = is_countable($results) ? count($results) : null;
        $items = [];

        foreach ($results as $result) {
            if ($result instanceof Campaign) {
                $items[] = $this->campaignNormalizer->normalizeSummary($result);
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

    public function fetch(int $campaignId, bool $includeEvents = true): array
    {
        $campaign = $this->campaignModel->getEntity($campaignId);

        if (!$campaign instanceof Campaign) {
            throw new NotFoundHttpException(sprintf('Campaign %d was not found.', $campaignId));
        }

        $this->permissionChecker->assertCanViewCampaign($campaign);

        return $this->campaignNormalizer->normalizeDetails($campaign, $includeEvents);
    }

    private function buildFilter(CampaignSearchQuery $query): array|string
    {
        $filter = $query->getSearch();

        if ($this->permissionChecker->canViewOtherCampaigns()) {
            return $filter;
        }

        $currentUser = $this->userHelper->getUser();
        if (null === $currentUser || null === $currentUser->getId()) {
            return [
                'string' => $filter,
                'force'  => [
                    [
                        'column' => 'c.id',
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
                    'column' => 'c.createdBy',
                    'expr'   => 'eq',
                    'value'  => $currentUser->getId(),
                ],
            ],
        ];
    }
}
