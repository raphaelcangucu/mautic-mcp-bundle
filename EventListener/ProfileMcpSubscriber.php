<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomTemplateEvent;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticMcpBundle\Application\System\McpTokenService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProfileMcpSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserHelper $userHelper,
        private McpTokenService $tokens,
        private RequestStack $requestStack,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [CoreEvents::VIEW_INJECT_CUSTOM_TEMPLATE => ['onTemplate', -50]];
    }

    public function onTemplate(CustomTemplateEvent $event): void
    {
        if ('@MauticUser/Profile/index.html.twig' !== $event->getTemplate() || !$event->checkRouteContext('mautic_user_account')) {
            return;
        }
        $user = $this->userHelper->getUser();
        if (!$user instanceof User) {
            return;
        }
        $request = $this->requestStack->getCurrentRequest();
        $endpoint = null === $request ? '/mcp' : $request->getSchemeAndHttpHost().$request->getBaseUrl().'/mcp';
        $event->setVars($event->getVars() + ['mcpAccess' => $this->tokens->current($user), 'mcpEndpoint' => $endpoint]);
        $event->setTemplate('@MauticMcp/Account/profile_mcp.html.twig');
    }
}
