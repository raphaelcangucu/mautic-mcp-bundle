<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Security;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PermissionChecker
{
    public function __construct(
        private CorePermissions $permissions,
    ) {
    }

    public function assertCanSearchContacts(): void
    {
        if (!$this->canViewOwnContacts() && !$this->canViewOtherContacts()) {
            throw new AccessDeniedException('You do not have permission to view contacts.');
        }
    }

    public function assertCanViewContact(Lead $contact): void
    {
        $this->assertCanSearchContacts();

        if (!$this->permissions->hasEntityAccess(
            'lead:leads:viewown',
            'lead:leads:viewother',
            $contact->getPermissionUser()
        )) {
            throw new AccessDeniedException(sprintf('You do not have permission to view contact %d.', $contact->getId()));
        }
    }

    public function assertCanSearchCampaigns(): void
    {
        if (!$this->canViewOwnCampaigns() && !$this->canViewOtherCampaigns()) {
            throw new AccessDeniedException('You do not have permission to view campaigns.');
        }
    }

    public function assertCanViewCampaign(Campaign $campaign): void
    {
        $this->assertCanSearchCampaigns();

        if (!$this->permissions->hasEntityAccess(
            'campaign:campaigns:viewown',
            'campaign:campaigns:viewother',
            $campaign->getCreatedBy()
        )) {
            throw new AccessDeniedException(sprintf('You do not have permission to view campaign %d.', $campaign->getId() ?? 0));
        }
    }

    public function canViewOwnContacts(): bool
    {
        return $this->isGrantedIfDefined('lead:leads:viewown');
    }

    public function canViewOtherContacts(): bool
    {
        return $this->isGrantedIfDefined('lead:leads:viewother');
    }

    public function canViewOwnCampaigns(): bool
    {
        return $this->isGrantedIfDefined('campaign:campaigns:viewown');
    }

    public function canViewOtherCampaigns(): bool
    {
        return $this->isGrantedIfDefined('campaign:campaigns:viewother');
    }

    private function isGrantedIfDefined(string $permission): bool
    {
        if (!$this->permissions->checkPermissionExists($permission)) {
            return false;
        }

        return $this->permissions->isGranted($permission);
    }
}
