<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Mcp\Tool;

use Mautic\UserBundle\Model\UserModel;
use Mautic\UserBundle\Security\UserTokenSetter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractMcpTool
{
    private ?RequestStack $requestStack = null;
    private ?TokenStorageInterface $tokenStorage = null;
    private ?UserTokenSetter $userTokenSetter = null;
    private ?UserModel $userModel = null;
    private ?KernelInterface $kernel = null;
    private bool $allowStdioAdminFallback = false;

    #[Required]
    final public function setExecutionContextDependencies(
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage,
        UserTokenSetter $userTokenSetter,
        UserModel $userModel,
        KernelInterface $kernel,
        bool $allowStdioAdminFallback,
    ): void {
        $this->requestStack    = $requestStack;
        $this->tokenStorage    = $tokenStorage;
        $this->userTokenSetter = $userTokenSetter;
        $this->userModel       = $userModel;
        $this->kernel          = $kernel;
        $this->allowStdioAdminFallback = $allowStdioAdminFallback;
    }

    protected function bootstrapExecution(): void
    {
        if (
            null === $this->requestStack
            || null === $this->tokenStorage
            || null === $this->userTokenSetter
            || null === $this->userModel
            || null === $this->kernel
        ) {
            throw new \LogicException('MCP tool execution context has not been wired.');
        }

        if (null !== $this->requestStack->getCurrentRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof UserInterface) {
            return;
        }

        if (!$this->allowStdioAdminFallback) {
            throw new \RuntimeException(
                'MCP stdio execution requires an authenticated user. '
                .'System administrator fallback is disabled by default and must be enabled explicitly for local/dev use.'
            );
        }

        $systemAdministrator = $this->userModel->getSystemAdministrator();
        $systemAdministratorId = $systemAdministrator?->getId();

        if (null === $systemAdministrator || null === $systemAdministratorId) {
            throw new \RuntimeException('Unable to resolve a published system administrator for MCP stdio tool execution.');
        }

        $this->userTokenSetter->setUser($systemAdministratorId);
    }
}
