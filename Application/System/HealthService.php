<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\System;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Symfony\Component\HttpKernel\KernelInterface;

final class HealthService
{
    public function __construct(
        private KernelInterface $kernel,
        private UserHelper $userHelper,
        private CorePermissions $permissions,
    ) {}

    public function status(): array
    {
        $user = $this->userHelper->getUser();

        return [
            'status'        => 'ok',
            'mauticVersion' => defined('MAUTIC_VERSION') ? MAUTIC_VERSION : 'unknown',
            'mcpVersion'    => '0.14.2',
            'phpVersion'    => PHP_VERSION,
            'environment'   => $this->kernel->getEnvironment(),
            'user'          => null === $user ? null : [
                'id'       => $user->getId(),
                'username' => $user->getUserIdentifier(),
                'name'     => $user->getName(),
                'role'     => $user->getRole()?->getName(),
            ],
            'permissions'   => $user?->isAdmin() ? ['*'] : $this->grantedPermissions(),
            'serverTime'    => gmdate(DATE_ATOM),
        ];
    }

    private function grantedPermissions(): array
    {
        $granted = [];
        foreach ($this->permissions->getAllPermissions() as $bundle => $levels) {
            foreach ($levels as $level => $allowed) {
                if ($allowed) {
                    $granted[] = $bundle.':'.$level;
                }
            }
        }

        return $granted;
    }
}
