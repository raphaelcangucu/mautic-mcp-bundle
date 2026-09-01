<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Controller;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\MauticMcpBundle\Application\System\McpTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class McpAccountController extends AbstractController
{
    public function rotate(Request $request, UserHelper $userHelper, McpTokenService $tokens): RedirectResponse
    {
        $this->validateCsrf($request);
        $tokens->rotate($this->user($userHelper));
        $this->addFlash('notice', 'Novo token MCP gerado. Atualize os clientes que utilizavam o token anterior.');

        return $this->redirectToRoute('mautic_user_account', ['_fragment' => 'mcp-access']);
    }

    public function revoke(Request $request, UserHelper $userHelper, McpTokenService $tokens): RedirectResponse
    {
        $this->validateCsrf($request);
        $tokens->revoke($this->user($userHelper));
        $this->addFlash('notice', 'Token MCP revogado.');

        return $this->redirectToRoute('mautic_user_account', ['_fragment' => 'mcp-access']);
    }

    private function validateCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('mautic_mcp_token', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function user(UserHelper $userHelper): User
    {
        $user = $userHelper->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
